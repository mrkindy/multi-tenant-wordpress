<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Cache;

use MrKindy\MultiTenantWordPress\Cache\ArrayCache;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use PHPUnit\Framework\TestCase;

final class ArrayCacheTest extends TestCase
{
    use CreatesTenant;

    public function testItStoresAndDeletesTenant(): void
    {
        $cache = $this->createCache();
        $tenant = $this->createTenant();

        $cache->set('tenant', $tenant, 0);
        self::assertSame($tenant, $cache->get('tenant'));

        $cache->delete('tenant');
        self::assertNull($cache->get('tenant'));
    }

    public function testItExpiresTenant(): void
    {
        $cache = $this->createCache();
        $cache->set('tenant', $this->createTenant(), 1);

        sleep(1);

        self::assertNull($cache->get('tenant'));
    }

    private function createCache(): ArrayCache
    {
        return new ArrayCache(
            new EncryptionService(EncryptionService::generateKey()),
        );
    }
}
