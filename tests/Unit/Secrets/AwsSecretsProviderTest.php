<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Secrets;

use MrKindy\MultiTenantWordPress\Contracts\AwsSecretsManagerClientInterface;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Secrets\AwsSecretsProvider;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

final class AwsSecretsProviderTest extends TestCase
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
        $client = $this->createStub(AwsSecretsManagerClientInterface::class);
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
        $client = $this->createStub(AwsSecretsManagerClientInterface::class);
        $client->method('getSecretString')->willReturn(null);

        $this->expectException(ConfigurationException::class);

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }

    public function testItRejectsMissingJsonPasswordKey(): void
    {
        $client = $this->createStub(AwsSecretsManagerClientInterface::class);
        $client
            ->method('getSecretString')
            ->willReturn('{"username":"tenant"}');

        $this->expectException(ConfigurationException::class);

        (new AwsSecretsProvider($client))
            ->getDatabasePassword($this->createTenant());
    }
}
