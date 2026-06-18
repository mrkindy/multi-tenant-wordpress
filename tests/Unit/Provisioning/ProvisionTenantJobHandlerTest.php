<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use DateTimeImmutable;
use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\DTO\TenantProvisioningResult;
use MrKindy\MultiTenantWordPress\Provisioning\ProvisionTenantJob;
use MrKindy\MultiTenantWordPress\Provisioning\ProvisionTenantJobHandler;
use MrKindy\MultiTenantWordPress\Provisioning\TenantProvisioner;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProvisionTenantJobHandlerTest extends TestCase
{
    private ProvisionTenantJobHandler $handler;
    private PdoTenantRepository $repository;
    private TenantProvisioner $provisioner;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PdoTenantRepository::class);
        $this->provisioner = $this->createMock(TenantProvisioner::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ProvisionTenantJobHandler(
            $this->repository,
            $this->provisioner,
            $this->logger,
        );
    }

    public function testItHandlesJobWithExistingTenant(): void
    {
        $tenant = $this->createTenant();
        $job = new ProvisionTenantJob('1');

        $this->repository->expects(self::once())
            ->method('findById')
            ->with('1')
            ->willReturn($tenant);

        $this->logger->expects(self::once())
            ->method('info')
            ->with('Handling provisioning job', ['tenant_id' => '1']);

        $this->provisioner->expects(self::once())
            ->method('provision')
            ->with(
                self::callback(fn (Tenant $t) => $t->id === '1'),
                self::isInstanceOf(ProvisioningAdminCredentials::class),
            )
            ->willReturn($this->createProvisioningResult($tenant));

        $this->handler->handle($job);
    }

    public function testItLogsErrorWhenTenantNotFound(): void
    {
        $job = new ProvisionTenantJob('999');

        $this->repository->expects(self::once())
            ->method('findById')
            ->with('999')
            ->willReturn(null);

        $this->logger->expects(self::once())
            ->method('error')
            ->with(
                'Provisioning failed: Tenant not found',
                ['tenant_id' => '999'],
            );

        $this->provisioner->expects(self::never())
            ->method('provision');

        $this->handler->handle($job);
    }

    public function testItUsesDefaultAdminCredentialsWhenNotProvided(): void
    {
        $tenant = $this->createTenant();
        $job = new ProvisionTenantJob('1');

        $this->repository->method('findById')->willReturn($tenant);

        $capturedAdmin = null;
        $this->provisioner->method('provision')
            ->willReturnCallback(function ($t, $admin) use (&$capturedAdmin) {
                $capturedAdmin = $admin;
                return $this->createProvisioningResult($t, $admin);
            });

        $this->handler->handle($job);

        self::assertInstanceOf(ProvisioningAdminCredentials::class, $capturedAdmin);
        // generateUsername returns 'admin_' + first 8 chars of md5(tenantId)
        // md5('1') = 'c4ca4238a0b923820dcc509a6f75849b', so first 8 chars = 'c4ca4238'
        self::assertSame('admin_c4ca4238', $capturedAdmin->username);
        self::assertSame('admin@shop.example.com', $capturedAdmin->email);
        self::assertSame(32, strlen($capturedAdmin->password)); // bin2hex(random_bytes(16)) = 32 chars
    }

    public function testItUsesCustomAdminCredentialsWhenProvided(): void
    {
        $tenant = $this->createTenant();
        $job = new ProvisionTenantJob(
            tenantId: '1',
            adminUsername: 'custom_admin',
            adminEmail: 'custom@example.com',
            adminPassword: 'custom_password_123',
        );

        $this->repository->method('findById')->willReturn($tenant);

        $capturedAdmin = null;
        $this->provisioner->method('provision')
            ->willReturnCallback(function ($t, $admin) use (&$capturedAdmin) {
                $capturedAdmin = $admin;
                return $this->createProvisioningResult($t, $admin);
            });

        $this->handler->handle($job);

        self::assertInstanceOf(ProvisioningAdminCredentials::class, $capturedAdmin);
        self::assertSame('custom_admin', $capturedAdmin->username);
        self::assertSame('custom@example.com', $capturedAdmin->email);
        self::assertSame('custom_password_123', $capturedAdmin->password);
    }

    public function testItGeneratesEmailFromDomain(): void
    {
        $tenant = $this->createTenant();
        $job = new ProvisionTenantJob(
            tenantId: '1',
            adminUsername: 'admin',
        );

        $this->repository->method('findById')->willReturn($tenant);

        $capturedAdmin = null;
        $this->provisioner->method('provision')
            ->willReturnCallback(function ($t, $admin) use (&$capturedAdmin) {
                $capturedAdmin = $admin;
                return $this->createProvisioningResult($t, $admin);
            });

        $this->handler->handle($job);

        self::assertSame('admin@shop.example.com', $capturedAdmin->email);
    }

    public function testItUsesNullLoggerByDefault(): void
    {
        $handler = new ProvisionTenantJobHandler(
            $this->repository,
            $this->provisioner,
        );

        $tenant = $this->createTenant();
        $job = new ProvisionTenantJob('1');

        $this->repository->method('findById')->willReturn($tenant);
        $this->provisioner->method('provision')
            ->willReturn($this->createProvisioningResult($tenant));

        // Should not throw when using NullLogger
        $handler->handle($job);

        self::assertTrue(true); // Test passes if no exception
    }

    private function createTenant(): Tenant
    {
        return new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'encrypted',
            status: 'pending',
            plan: 'business',
            metadata: [],
        );
    }

    private function createProvisioningResult(
        Tenant $tenant,
        ?ProvisioningAdminCredentials $admin = null,
    ): TenantProvisioningResult {
        return new TenantProvisioningResult(
            tenant: $tenant,
            adminCredentials: $admin ?? new ProvisioningAdminCredentials(
                username: 'admin',
                email: 'admin@example.com',
                password: 'password123',
            ),
            installedAt: new DateTimeImmutable(),
            pageIds: [],
        );
    }
}
