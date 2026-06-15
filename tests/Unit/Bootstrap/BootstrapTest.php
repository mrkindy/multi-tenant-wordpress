<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Bootstrap;

use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Cache\ArrayCache;
use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    use CreatesTenant;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItBootstrapsTenantBeforeWordPressLoads(): void
    {
        $_SERVER['HTTP_HOST'] = 'SHOP.EXAMPLE.COM:443';
        $tenant = $this->createTenant();
        $repository = new class ($tenant) implements TenantRepositoryInterface {
            public function __construct(private readonly Tenant $tenant)
            {
            }

            public function findByDomain(string $domain): ?Tenant
            {
                return $domain === $this->tenant->domain ? $this->tenant : null;
            }
        };
        $secrets = new class () implements SecretProviderInterface {
            public function getDatabasePassword(Tenant $tenant): string
            {
                return 'database-secret';
            }
        };
        $config = new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'control_reader',
            controlDatabasePassword: 'control-secret',
            trustedDomainSuffixes: ['*.example.com'],
            tenantRepository: $repository,
            customSecretProvider: $secrets,
            customCache: new ArrayCache(),
        );

        self::assertSame($tenant, Bootstrap::boot($config));
        self::assertSame('tenant_1', constant('DB_NAME'));
        self::assertSame('database-secret', constant('DB_PASSWORD'));
    }

    public function testItRejectsInvalidRequestBeforeTenantLookup(): void
    {
        $_SERVER['HTTP_HOST'] = '127.0.0.1';
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository->expects(self::never())->method('findByDomain');

        $this->expectException(InvalidDomainException::class);

        Bootstrap::boot(new Config(
            controlDatabaseHost: 'control-db',
            controlDatabasePort: 3306,
            controlDatabaseName: 'wordpress_control',
            controlDatabaseUser: 'control_reader',
            controlDatabasePassword: 'control-secret',
            tenantRepository: $repository,
            customSecretProvider: $this->createStub(SecretProviderInterface::class),
        ));
    }

    public function testItWrapsUnexpectedTechnicalFailures(): void
    {
        $_SERVER['HTTP_HOST'] = 'shop.example.com';
        $repository = $this->createStub(TenantRepositoryInterface::class);
        $repository
            ->method('findByDomain')
            ->willThrowException(new \RuntimeException('private detail'));

        try {
            Bootstrap::boot(new Config(
                controlDatabaseHost: 'control-db',
                controlDatabasePort: 3306,
                controlDatabaseName: 'wordpress_control',
                controlDatabaseUser: 'control_reader',
                controlDatabasePassword: 'control-secret',
                tenantRepository: $repository,
                customSecretProvider: $this->createStub(SecretProviderInterface::class),
            ));
            self::fail('Expected bootstrap failure.');
        } catch (ConfigurationException $exception) {
            self::assertSame(
                'Tenant configuration could not be loaded.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(\RuntimeException::class, $exception->getPrevious());
        }
    }
}
