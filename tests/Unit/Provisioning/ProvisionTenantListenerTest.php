<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\JobDispatcherInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Events\TenantCreated;
use MrKindy\MultiTenantWordPress\Provisioning\ProvisionTenantJob;
use MrKindy\MultiTenantWordPress\Provisioning\ProvisionTenantListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProvisionTenantListenerTest extends TestCase
{
    private ProvisionTenantListener $listener;
    private JobDispatcherInterface $dispatcher;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(JobDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->listener = new ProvisionTenantListener($this->dispatcher, $this->logger);
    }

    public function testItDispatchesJobOnTenantCreated(): void
    {
        $tenant = $this->createTenant();
        $event = new TenantCreated($tenant);

        $this->dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(ProvisionTenantJob::class));

        $this->listener->handle($event);
    }

    public function testItPassesTenantIdToJob(): void
    {
        $tenant = $this->createTenant();
        $event = new TenantCreated($tenant);

        $capturedJob = null;
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($job) use (&$capturedJob) {
                $capturedJob = $job;
            });

        $this->listener->handle($event);

        self::assertInstanceOf(ProvisionTenantJob::class, $capturedJob);
        self::assertSame('1', $capturedJob->tenantId);
    }

    public function testItPassesAdminCredentialsToJob(): void
    {
        $tenant = $this->createTenant();
        $event = new TenantCreated(
            tenant: $tenant,
            adminUsername: 'custom_admin',
            adminEmail: 'custom@example.com',
            adminPassword: 'custom_pass',
        );

        $capturedJob = null;
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($job) use (&$capturedJob) {
                $capturedJob = $job;
            });

        $this->listener->handle($event);

        self::assertSame('custom_admin', $capturedJob->adminUsername);
        self::assertSame('custom@example.com', $capturedJob->adminEmail);
        self::assertSame('custom_pass', $capturedJob->adminPassword);
    }

    public function testItLogsEventReceipt(): void
    {
        $tenant = $this->createTenant();
        $event = new TenantCreated($tenant);

        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('TenantCreated event received'),
                self::arrayHasKey('tenant_id'),
            );

        $this->listener->handle($event);
    }

    public function testItLogsJobDispatch(): void
    {
        $tenant = $this->createTenant();
        $event = new TenantCreated($tenant);

        $this->logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('ProvisionTenantJob dispatched'),
                self::arrayHasKey('tenant_id'),
            );

        $this->listener->handle($event);
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
}
