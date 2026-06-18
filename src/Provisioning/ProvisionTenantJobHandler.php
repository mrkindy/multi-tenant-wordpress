<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shared handler that executes the provisioning logic for a job.
 * Used by both synchronous and asynchronous dispatchers.
 */
final readonly class ProvisionTenantJobHandler
{
    public function __construct(
        private PdoTenantRepository $repository,
        private TenantProvisioner $provisioner,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function handle(ProvisionTenantJob $job): void
    {
        $tenant = $this->repository->findById($job->tenantId);

        if ($tenant === null) {
            $this->logger->error('Provisioning failed: Tenant not found', [
                'tenant_id' => $job->tenantId
            ]);
            return;
        }

        // Resolve admin credentials (use defaults from job if not provided)
        $admin = new ProvisioningAdminCredentials(
            username: $job->adminUsername ?? $job->generateUsername($tenant->id),
            email: $job->adminEmail ?? $job->generateEmail($tenant->domain),
            password: $job->adminPassword ?? $job->generatePassword(),
        );

        $this->logger->info('Handling provisioning job', ['tenant_id' => $tenant->id]);

        // The actual heavy lifting
        $this->provisioner->provision($tenant, $admin);
    }
}