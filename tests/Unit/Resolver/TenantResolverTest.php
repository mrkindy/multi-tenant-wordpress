<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Resolver;

use MrKindy\MultiTenantWordPress\Cache\ArrayCache;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\Exceptions\TenantNotFoundException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantSuspendedException;
use MrKindy\MultiTenantWordPress\Resolver\TenantResolver;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

final class TenantResolverTest extends TestCase
{
    use CreatesTenant;

    public function testItResolvesAndCachesAnActiveTenant(): void
    {
        $tenant = $this->createTenant();
        $repository = $this->createMock(TenantRepositoryInterface::class);
        $repository
            ->expects(self::once())
            ->method('findByDomain')
            ->with('shop.example.com')
            ->willReturn($tenant);
        $resolver = new TenantResolver($repository, new ArrayCache(), 60);

        self::assertSame($tenant, $resolver->resolve('shop.example.com'));
        self::assertSame($tenant, $resolver->resolve('shop.example.com'));
    }

    public function testItThrowsWhenTenantDoesNotExist(): void
    {
        $repository = $this->createStub(TenantRepositoryInterface::class);
        $repository->method('findByDomain')->willReturn(null);

        $this->expectException(TenantNotFoundException::class);

        (new TenantResolver($repository, new ArrayCache()))
            ->resolve('missing.example.com');
    }

    public function testItRejectsSuspendedTenant(): void
    {
        $repository = $this->createStub(TenantRepositoryInterface::class);
        $repository
            ->method('findByDomain')
            ->willReturn($this->createTenant('suspended'));

        $this->expectException(TenantSuspendedException::class);

        (new TenantResolver($repository, new ArrayCache()))
            ->resolve('shop.example.com');
    }
}
