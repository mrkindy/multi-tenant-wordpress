<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Secrets;

use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Secrets\EnvSecretsProvider;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

final class EnvSecretsProviderTest extends TestCase
{
    use CreatesTenant;

    protected function tearDown(): void
    {
        putenv('TENANT_DATABASE_PASSWORD');
    }

    public function testItLoadsPasswordFromEnvironment(): void
    {
        putenv('TENANT_DATABASE_PASSWORD=secret');

        self::assertSame(
            'secret',
            (new EnvSecretsProvider())->getDatabasePassword($this->createTenant()),
        );
    }

    public function testItRejectsMissingSecret(): void
    {
        $this->expectException(ConfigurationException::class);

        (new EnvSecretsProvider())->getDatabasePassword($this->createTenant());
    }

    public function testItRejectsMalformedEnvironmentReference(): void
    {
        $this->expectException(ConfigurationException::class);

        (new EnvSecretsProvider())->getDatabasePassword(
            $this->createTenant(secretReference: 'tenant-password'),
        );
    }
}
