<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Cache;

use MrKindy\MultiTenantWordPress\Cache\ArrayCache;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

final class ArrayCacheTest extends TestCase
{
    use CreatesTenant;

    public function testItStoresAndDeletesTenant(): void
    {
        $cache = new ArrayCache();
        $tenant = $this->createTenant();

        $cache->set('tenant', $tenant, 0);
        self::assertSame($tenant, $cache->get('tenant'));

        $cache->delete('tenant');
        self::assertNull($cache->get('tenant'));
    }

    public function testItExpiresTenant(): void
    {
        $cache = new ArrayCache();
        $cache->set('tenant', $this->createTenant(), 1);

        sleep(1);

        self::assertNull($cache->get('tenant'));
    }
}
