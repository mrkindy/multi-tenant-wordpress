<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Config;

use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
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
            encryptionKey: EncryptionService::generateKey(),
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
            encryptionKey: EncryptionService::generateKey(),
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
            encryptionKey: '',
            secretProvider: 'vault',
            cacheProvider: 'redis',
            customSecretProvider: self::createStub(SecretProviderInterface::class),
            customCache: self::createStub(CacheInterface::class),
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
            encryptionKey: EncryptionService::generateKey(),
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
            encryptionKey: EncryptionService::generateKey(),
            cacheTtlSeconds: -1,
        );
    }

    public function testItRejectsInvalidEncryptionKeyForDefaultCache(): void
    {
        $this->expectException(ConfigurationException::class);

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: 'invalid',
        );
    }

    public function testItRejectsEmptyControlDatabaseHost(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database configuration is incomplete.');

        new Config(
            controlDatabaseHost: '',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsEmptyControlDatabaseName(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database configuration is incomplete.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: '',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsEmptyControlDatabaseUser(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database configuration is incomplete.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: '',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsInvalidDatabaseName(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database identifier is invalid.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'invalid;name',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsZeroPort(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database port is invalid.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 0,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsNegativePort(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database port is invalid.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: -1,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItAcceptsValidConfiguration(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );

        self::assertSame('control-db', $config->controlDatabaseHost);
        self::assertSame(3306, $config->controlDatabasePort);
        self::assertSame('wordpress_control', $config->controlDatabaseName);
        self::assertSame('reader', $config->controlDatabaseUser);
        self::assertSame('secret', $config->controlDatabasePassword);
        self::assertSame('encrypted', $config->secretProvider);
        self::assertSame('array', $config->cacheProvider);
        self::assertSame([], $config->trustedDomainSuffixes);
        self::assertFalse($config->allowLocalhost);
        self::assertSame(60, $config->cacheTtlSeconds);
        self::assertSame('us-east-1', $config->awsRegion);
        self::assertSame('password', $config->awsSecretPasswordKey);
    }

    public function testItAcceptsCustomValues(): void
    {
        $config = new Config(
            controlDatabaseHost: 'custom-db',
            controlDatabasePort: 3307,
            controlDatabaseName: 'custom_control',
            controlDatabaseUser: 'custom_reader',
            controlDatabasePassword: 'custom_secret',
            secretProvider: Config::SECRET_PROVIDER_AWS,
            encryptionKey: EncryptionService::generateKey(),
            cacheProvider: Config::CACHE_PROVIDER_ARRAY,
            trustedDomainSuffixes: ['*.example.com', '*.test.com'],
            allowLocalhost: true,
            cacheTtlSeconds: 120,
            awsRegion: 'eu-west-1',
            awsSecretPasswordKey: 'db_password',
            wpPath: '/var/www/wordpress',
            databaseNamePrefix: 'wp_',
            databaseUserPrefix: 'wpuser_',
        );

        self::assertSame('custom-db', $config->controlDatabaseHost);
        self::assertSame(3307, $config->controlDatabasePort);
        self::assertSame('aws', $config->secretProvider);
        self::assertSame(['*.example.com', '*.test.com'], $config->trustedDomainSuffixes);
        self::assertTrue($config->allowLocalhost);
        self::assertSame(120, $config->cacheTtlSeconds);
        self::assertSame('eu-west-1', $config->awsRegion);
        self::assertSame('db_password', $config->awsSecretPasswordKey);
        self::assertSame('/var/www/wordpress', $config->wpPath);
        self::assertSame('wp_', $config->databaseNamePrefix);
        self::assertSame('wpuser_', $config->databaseUserPrefix);
    }

    public function testItAcceptsZeroCacheTtl(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
            cacheTtlSeconds: 0,
        );

        self::assertSame(0, $config->cacheTtlSeconds);
    }

    public function testItSkipsEncryptionValidationWithCustomCache(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: '', // Invalid key, but custom cache provided
            customCache: self::createStub(CacheInterface::class),
        );

        self::assertNotNull($config->customCache);
    }

    public function testItSkipsProviderValidationWithCustomProvider(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: '',
            secretProvider: 'invalid_provider', // Would fail without custom provider
            customSecretProvider: self::createStub(SecretProviderInterface::class),
            customCache: self::createStub(CacheInterface::class),
        );

        self::assertSame('invalid_provider', $config->secretProvider);
        self::assertNotNull($config->customSecretProvider);
    }

    public function testItRejectsUnsupportedCacheProvider(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Cache provider is not supported.');

        new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
            cacheProvider: 'redis', // Not supported without custom cache
        );
    }

    public function testItRejectsHostWithSemicolon(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database identifier is invalid.');

        new Config(
            controlDatabaseHost: 'db;DROP TABLE users;',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsHostWithEquals(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database identifier is invalid.');

        new Config(
            controlDatabaseHost: 'db=attacker',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItRejectsHostWithNullByte(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Control database identifier is invalid.');

        new Config(
            controlDatabaseHost: "db\x00attacker",
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );
    }

    public function testItAcceptsDatabaseNameWithUnderscoreAndDollar(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_$control_123',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );

        self::assertSame('wordpress_$control_123', $config->controlDatabaseName);
    }

    public function testItAcceptsDatabaseNameWithHyphen(): void
    {
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress-control',
            controlDatabaseUser: 'reader',
            controlDatabasePassword: 'secret',
            encryptionKey: EncryptionService::generateKey(),
        );

        self::assertSame('wordpress-control', $config->controlDatabaseName);
    }
}
