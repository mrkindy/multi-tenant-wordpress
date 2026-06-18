<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use DateTimeImmutable;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\DTO\TenantProvisioningResult;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates the complete tenant provisioning process.
 *
 * This class coordinates all provisioning steps:
 * 1. Database creation
 * 2. Database user creation
 * 3. WordPress schema installation
 * 4. Default data seeding
 * 5. Admin account creation
 * 6. Mark installation complete
 *
 * All steps are idempotent and safe to retry.
 */
readonly class TenantProvisioner
{
    public function __construct(
        private PdoTenantRepository $repository,
        private SecretProviderInterface $secretProvider,
        private DatabaseManager $databaseManager,
        private WordPressInstaller $wordpressInstaller,
        private DefaultDataSeeder $dataSeeder,
        private AdminAccountSeeder $adminSeeder,
        private ?AdditionalSeeder $additionalSeeder = null,
        private ?LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Provision a complete WordPress installation for a tenant.
     *
     * This method is idempotent - safe to call multiple times.
     * If the tenant is already installed, returns the cached result.
     *
     * @param Tenant $tenant The tenant to provision
     * @param ProvisioningAdminCredentials $admin The WordPress admin credentials
     * @return TenantProvisioningResult The provisioning result
     * @throws TenantProvisioningException If provisioning fails
     */
    public function provision(
        Tenant $tenant,
        ProvisioningAdminCredentials $admin,
    ): TenantProvisioningResult {
        $startTime = microtime(true);
        $this->logger->info('Starting tenant provisioning', [
            'tenant_id' => $tenant->id,
            'domain' => $tenant->domain,
        ]);

        // Check if already installed
        if ($tenant->status === 'installed') {
            $this->logger->info('Tenant already installed, skipping provisioning', [
                'tenant_id' => $tenant->id,
            ]);

            return $this->createResultFromTenant($tenant, $admin);
        }

        // Get the decrypted database password
        try {
            $password = $this->secretProvider->getDatabasePassword($tenant);
        } catch (\Throwable $e) {
            $this->logFailure($tenant, 'password_retrieval', $e);
            throw new TenantProvisioningException(
                "Failed to retrieve database password: {$e->getMessage()}",
                'password_retrieval',
                $tenant->id,
                $e,
            );
        }

        // Update status to installing
        $this->updateTenantStatus($tenant->id, 'installing');

        try {
            // Step 1: Create database
            $this->logger->debug('Creating database', [
                'tenant_id' => $tenant->id,
                'database' => $tenant->databaseName,
            ]);
            $this->databaseManager->createDatabase($tenant->databaseName);

            // Step 2: Create database user
            $this->logger->debug('Creating database user', [
                'tenant_id' => $tenant->id,
                'user' => $tenant->databaseUser,
            ]);
            $this->databaseManager->createDatabaseUser(
                $tenant->databaseUser,
                $password,
                $tenant->databaseName,
            );

            // Step 3: Install WordPress schema
            $this->logger->debug('Installing WordPress schema', [
                'tenant_id' => $tenant->id,
            ]);
            $this->wordpressInstaller->install($tenant, $password);

            // Step 4: Seed default data
            $this->logger->debug('Seeding default data', [
                'tenant_id' => $tenant->id,
            ]);
            $pageIds = $this->dataSeeder->seed($tenant, $password, $admin);

            // Step 5: Create admin account
            $this->logger->debug('Creating admin account', [
                'tenant_id' => $tenant->id,
                'username' => $admin->username,
            ]);
            $this->adminSeeder->createAdmin($tenant, $password, $admin);

            // Step 6: Additional seeding (stub)
            if ($this->additionalSeeder !== null) {
                $this->logger->debug('Seeding additional data', [
                    'tenant_id' => $tenant->id,
                ]);
                $this->additionalSeeder->seed($tenant, $password);
            }

            // Step 7: Mark installation complete
            $this->markInstallationComplete($tenant->id, $admin);

            $elapsedTime = microtime(true) - $startTime;
            $this->logger->info('Tenant provisioning completed', [
                'tenant_id' => $tenant->id,
                'elapsed_seconds' => round($elapsedTime, 3),
            ]);

            // Create and return result
            $result = new TenantProvisioningResult(
                tenant: $this->repository->findById($tenant->id) ?? $tenant,
                adminCredentials: $admin,
                installedAt: new DateTimeImmutable(),
                pageIds: $pageIds,
            );

            return $result;
        } catch (TenantProvisioningException $e) {
            $this->handleFailure($tenant, $e);
            throw $e;
        } catch (\Throwable $e) {
            $provisionException = new TenantProvisioningException(
                "Provisioning failed: {$e->getMessage()}",
                'unknown',
                $tenant->id,
                $e,
            );
            $this->handleFailure($tenant, $provisionException);
            throw $provisionException;
        }
    }

    /**
     * Update tenant status in the database.
     */
    private function updateTenantStatus(string $tenantId, string $status): void
    {
        try {
            $this->repository->updateStatus($tenantId, $status);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to update tenant status', [
                'tenant_id' => $tenantId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mark tenant installation as complete.
     */
    private function markInstallationComplete(
        string $tenantId,
        ProvisioningAdminCredentials $admin,
    ): void {
        try {
            $this->repository->markInstalled(
                $tenantId,
                $admin->username,
                $admin->email,
                new DateTimeImmutable(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to mark tenant as installed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle provisioning failure.
     */
    private function handleFailure(Tenant $tenant, TenantProvisioningException $exception): void
    {
        $this->logFailure($tenant, $exception->step, $exception);

        try {
            $this->repository->recordFailure($tenant->id, $exception->getMessage());
            $this->repository->incrementAttempts($tenant->id);
            $this->repository->updateStatus($tenant->id, 'failed');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to record provisioning failure', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log a provisioning failure.
     */
    private function logFailure(Tenant $tenant, string $step, \Throwable $exception): void
    {
        $this->logger->error('Tenant provisioning failed', [
            'tenant_id' => $tenant->id,
            'domain' => $tenant->domain,
            'step' => $step,
            'error' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }

    /**
     * Create a result object from an already-installed tenant.
     */
    private function createResultFromTenant(
        Tenant $tenant,
        ProvisioningAdminCredentials $admin,
    ): TenantProvisioningResult {
        // Try to get installed_at from metadata
        $installedAt = new DateTimeImmutable();
        if (isset($tenant->metadata['installed_at']) && is_string($tenant->metadata['installed_at'])) {
            $timestamp = strtotime($tenant->metadata['installed_at']);
            if ($timestamp !== false) {
                $installedAt = new DateTimeImmutable('@' . $timestamp);
            }
        }

        return new TenantProvisioningResult(
            tenant: $tenant,
            adminCredentials: $admin,
            installedAt: $installedAt,
            pageIds: [],
        );
    }
}
