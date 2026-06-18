<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Secrets;

use Aws\Exception\AwsException;
use Aws\Result;
use MrKindy\MultiTenantWordPress\Contracts\AwsSecretsManagerClientInterface;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Secrets\AwsSecretsProvider;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AwsSecretsProvider using a mock AwsSecretsManagerClientInterface.
 * We test the AwsSecretsManagerClient wrapper indirectly through the provider.
 */
final class AwsSecretsManagerClientTest extends TestCase
{
    use CreatesTenant;

    public function testItReturnsRawSecretString(): void
    {
        $client = $this->createMock(AwsSecretsManagerClientInterface::class);
        $client
            ->expects(self::once())
            ->method('getSecretString')
            ->with('tenant/database')
            ->willReturn('raw-password');

        $provider = new AwsSecretsProvider($client);

        self::assertSame(
            'raw-password',
            $provider->getDatabasePassword(
                $this->createTenant(secretReference: 'tenant/database'),
            ),
        );
    }

    public function testItExtractsPasswordFromJsonSecret(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{"database_password":"secret"}');
        $provider = new AwsSecretsProvider($client, 'database_password');

        self::assertSame(
            'secret',
            $provider->getDatabasePassword($this->createTenant()),
        );
    }

    public function testItRejectsMissingSecretString(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client->method('getSecretString')->willReturn(null);

        $this->expectException(ConfigurationException::class);

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItRejectsMissingJsonPasswordKey(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{"username":"tenant"}');

        $this->expectException(ConfigurationException::class);

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItThrowsConfigurationExceptionOnAwsError(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willThrowException(new ConfigurationException('Tenant database secret is unavailable.'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database secret is unavailable.');

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItHandlesEmptySecretString(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client->method('getSecretString')->willReturn('');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database secret is unavailable.');

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItHandlesJsonSecretWithEmptyPassword(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{"password":""}');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database password is unavailable.');

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItHandlesArnAsSecretId(): void
    {
        $arn = 'arn:aws:secretsmanager:us-east-1:123456789:secret:tenant/database/1-AbCdEf';
        $client = $this->createMock(AwsSecretsManagerClientInterface::class);
        $client
            ->expects(self::once())
            ->method('getSecretString')
            ->with($arn)
            ->willReturn('password');

        $provider = new AwsSecretsProvider($client);

        self::assertSame(
            'password',
            $provider->getDatabasePassword(
                $this->createTenant(secretReference: $arn),
            ),
        );
    }

    public function testItHandlesNonJsonSecret(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client->method('getSecretString')->willReturn('plain-text-password');

        $provider = new AwsSecretsProvider($client);

        self::assertSame(
            'plain-text-password',
            $provider->getDatabasePassword($this->createTenant()),
        );
    }

    public function testItHandlesWhitespaceInJsonSecret(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('  {"password":"secret123"}  ');

        $provider = new AwsSecretsProvider($client);

        self::assertSame(
            'secret123',
            $provider->getDatabasePassword($this->createTenant()),
        );
    }

    public function testItRejectsInvalidJson(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{invalid json}');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database secret is malformed.');

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItUsesCustomPasswordKey(): void
    {
        $client = self::createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{"db_pass":"mysecret","password":"wrong"}');

        $provider = new AwsSecretsProvider($client, 'db_pass');

        self::assertSame(
            'mysecret',
            $provider->getDatabasePassword($this->createTenant()),
        );
    }
}
