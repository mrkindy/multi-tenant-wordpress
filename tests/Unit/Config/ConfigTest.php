<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Config;

use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testItRejectsDsnInjectionInControlHost(): void
    {
        $this->expectException(ConfigurationException::class);

        new Config(
            controlDatabaseHost: 'db;dbname=attacker',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
        );
    }

    public function testItRejectsUnsupportedProvider(): void
    {
        $this->expectException(ConfigurationException::class);

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            secretProvider: 'unknown',
        );
    }

    public function testCustomProvidersPermitCustomSelectionNames(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            secretProvider: 'vault',
            cacheProvider: 'redis',
            customSecretProvider: $this->createStub(SecretProviderInterface::class),
            customCache: $this->createStub(CacheInterface::class),
        );

        self::assertSame('vault', $config->secretProvider);
        self::assertSame('redis', $config->cacheProvider);
    }

    public function testItRejectsInvalidPort(): void
    {
        $this->expectException(ConfigurationException::class);

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 70000,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
        );
    }

    public function testItRejectsNegativeCacheTtl(): void
    {
        $this->expectException(ConfigurationException::class);

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            cacheTtlSeconds: -1,
        );
    }
}
