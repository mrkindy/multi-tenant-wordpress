<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\Provisioning\ProvisionTenantJob;
use PHPUnit\Framework\TestCase;

final class ProvisionTenantJobTest extends TestCase
{
    public function testItStoresTenantId(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        self::assertSame('123', $job->tenantId);
    }

    public function testItStoresOptionalAdminCredentials(): void
    {
        $job = new ProvisionTenantJob(
            tenantId: '123',
            adminUsername: 'admin',
            adminEmail: 'admin@example.com',
            adminPassword: 'secret123',
        );

        self::assertSame('123', $job->tenantId);
        self::assertSame('admin', $job->adminUsername);
        self::assertSame('admin@example.com', $job->adminEmail);
        self::assertSame('secret123', $job->adminPassword);
    }

    public function testItGeneratesPassword(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $password = $job->generatePassword();

        self::assertSame(32, strlen($password)); // 16 bytes = 32 hex chars
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $password);
    }

    public function testItGeneratesUniquePasswords(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $password1 = $job->generatePassword();
        $password2 = $job->generatePassword();

        self::assertNotSame($password1, $password2);
    }

    public function testItGeneratesUsername(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $username = $job->generateUsername('123');

        self::assertStringStartsWith('admin_', $username);
        self::assertSame(14, strlen($username)); // admin_ + 8 chars from md5
    }

    public function testItGeneratesConsistentUsernameForSameTenant(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $username1 = $job->generateUsername('123');
        $username2 = $job->generateUsername('123');

        self::assertSame($username1, $username2);
    }

    public function testItGeneratesDifferentUsernamesForDifferentTenants(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $username1 = $job->generateUsername('123');
        $username2 = $job->generateUsername('456');

        self::assertNotSame($username1, $username2);
    }

    public function testItGeneratesEmail(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        $email = $job->generateEmail('shop.example.com');

        self::assertSame('admin@shop.example.com', $email);
    }

    public function testItHandlesNullOptionalValues(): void
    {
        $job = new ProvisionTenantJob(tenantId: '123');

        self::assertNull($job->adminUsername);
        self::assertNull($job->adminEmail);
        self::assertNull($job->adminPassword);
    }
}
