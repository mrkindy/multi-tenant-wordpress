<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;
use MrKindy\MultiTenantWordPress\Provisioning\AdminAccountSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\DatabaseManager;
use MrKindy\MultiTenantWordPress\Provisioning\DefaultDataSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\TenantProvisioner;
use MrKindy\MultiTenantWordPress\Provisioning\AdditionalSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\WordPressInstaller;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class TenantProvisionerTest extends TestCase
{
    private TenantProvisioner $provisioner;
    private PdoTenantRepository $repository;
    private SecretProviderInterface $secretProvider;
    private DatabaseManager $databaseManager;
    private WordPressInstaller $wordpressInstaller;
    private DefaultDataSeeder $dataSeeder;
    private AdminAccountSeeder $adminSeeder;
    private AdditionalSeeder $additionalSeeder;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PdoTenantRepository::class);
        $this->secretProvider = $this->createMock(SecretProviderInterface::class);
        $this->databaseManager = $this->createMock(DatabaseManager::class);
        $this->wordpressInstaller = $this->createMock(WordPressInstaller::class);
        $this->dataSeeder = $this->createMock(DefaultDataSeeder::class);
        $this->adminSeeder = $this->createMock(AdminAccountSeeder::class);
        $this->additionalSeeder = $this->createMock(additionalSeeder::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->provisioner = new TenantProvisioner(
            $this->repository,
            $this->secretProvider,
            $this->databaseManager,
            $this->wordpressInstaller,
            $this->dataSeeder,
            $this->adminSeeder,
            $this->additionalSeeder,
            $this->logger,
        );
    }

    public function testItSkipsProvisioningForAlreadyInstalledTenant(): void
    {
        $tenant = $this->createTenant('installed');
        $admin = $this->createAdminCredentials();

        $this->logger->expects(self::exactly(2))
            ->method('info')
            ->with(self::logicalOr(
                self::stringContains('Starting tenant provisioning'),
                self::stringContains('already installed'),
            ));

        $result = $this->provisioner->provision($tenant, $admin);

        self::assertSame($tenant, $result->tenant);
        self::assertSame($admin, $result->adminCredentials);
    }

    public function testItThrowsExceptionWhenPasswordRetrievalFails(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->expects(self::once())
            ->method('getDatabasePassword')
            ->willThrowException(new \Exception('Secret not found'));

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Failed to retrieve database password');

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItUpdatesStatusToInstalling(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->expects(self::once())
            ->method('updateStatus')
            ->with($tenant->id, 'installing');

        // Mock successful provisioning
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');
        $this->wordpressInstaller->method('install');
        $this->dataSeeder->method('seed')->willReturn([]);
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItCreatesDatabaseAndUser(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->method('updateStatus');

        $this->databaseManager->expects(self::once())
            ->method('createDatabase')
            ->with($tenant->databaseName);

        $this->databaseManager->expects(self::once())
            ->method('createDatabaseUser')
            ->with($tenant->databaseUser, 'secret123', $tenant->databaseName);

        // Mock remaining steps
        $this->wordpressInstaller->method('install');
        $this->dataSeeder->method('seed')->willReturn([]);
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItInstallsWordPressSchema(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->method('updateStatus');
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');

        $this->wordpressInstaller->expects(self::once())
            ->method('install')
            ->with($tenant, 'secret123');

        // Mock remaining steps
        $this->dataSeeder->method('seed')->willReturn([]);
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItSeedsDefaultData(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->method('updateStatus');
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');
        $this->wordpressInstaller->method('install');

        $this->dataSeeder->expects(self::once())
            ->method('seed')
            ->with($tenant, 'secret123', $admin)
            ->willReturn(['home' => 1, 'privacy-policy' => 2]);

        // Mock remaining steps
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $result = $this->provisioner->provision($tenant, $admin);

        self::assertSame(['home' => 1, 'privacy-policy' => 2], $result->pageIds);
    }

    public function testItCreatesAdminAccount(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->method('updateStatus');
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');
        $this->wordpressInstaller->method('install');
        $this->dataSeeder->method('seed')->willReturn([]);

        $this->adminSeeder->expects(self::once())
            ->method('createAdmin')
            ->with($tenant, 'secret123', $admin);

        // Mock remaining steps
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItMarksInstallationComplete(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');
        $this->repository->method('updateStatus');
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');
        $this->wordpressInstaller->method('install');
        $this->dataSeeder->method('seed')->willReturn([]);
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');

        $this->repository->expects(self::once())
            ->method('markInstalled')
            ->with(
                $tenant->id,
                $admin->username,
                $admin->email,
                self::isInstanceOf(\DateTimeImmutable::class),
            );

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItRecordsFailureOnException(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');

        // Make database creation fail
        $this->databaseManager->method('createDatabase')
            ->willThrowException(new TenantProvisioningException(
                'DB error',
                'database_creation',
                $tenant->id,
            ));

        $this->repository->expects(self::once())
            ->method('recordFailure')
            ->with($tenant->id, self::stringContains('DB error'));

        $this->repository->expects(self::once())
            ->method('incrementAttempts')
            ->with($tenant->id);

        // updateStatus is called twice: once for 'installing', once for 'failed'
        $this->repository->expects(self::exactly(2))
            ->method('updateStatus');

        $this->expectException(TenantProvisioningException::class);

        $this->provisioner->provision($tenant, $admin);
    }

    public function testItLogsProvisioningStart(): void
    {
        $tenant = $this->createTenant('pending');
        $admin = $this->createAdminCredentials();

        $this->secretProvider->method('getDatabasePassword')->willReturn('secret123');

        // Use callback to verify the first call contains 'Starting tenant provisioning'
        $callCount = 0;
        $this->logger->expects(self::atLeastOnce())
            ->method('info')
            ->willReturnCallback(function (string $message) use (&$callCount): void {
                if ($callCount === 0) {
                    self::assertStringContainsString('Starting tenant provisioning', $message);
                }
                $callCount++;
            });

        // Mock all steps
        $this->repository->method('updateStatus');
        $this->databaseManager->method('createDatabase');
        $this->databaseManager->method('createDatabaseUser');
        $this->wordpressInstaller->method('install');
        $this->dataSeeder->method('seed')->willReturn([]);
        $this->adminSeeder->method('createAdmin');
        $this->additionalSeeder->method('seed');
        $this->repository->method('markInstalled');

        $this->provisioner->provision($tenant, $admin);
    }

    private function createTenant(string $status): Tenant
    {
        return new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'encrypted_password',
            status: $status,
            plan: 'business',
            metadata: [],
        );
    }

    private function createAdminCredentials(): ProvisioningAdminCredentials
    {
        return new ProvisioningAdminCredentials(
            username: 'admin',
            email: 'admin@example.com',
            password: 'secure_password_123',
        );
    }
}
