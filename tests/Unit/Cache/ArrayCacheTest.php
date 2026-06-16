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
        self::assertEquals($tenant, $cache->get('tenant'));

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

    public function testItReturnsNullForMissingKey(): void
    {
        $cache = $this->createCache();

        self::assertNull($cache->get('non-existent-key'));
    }

    public function testItReturnsNullForExpiredItem(): void
    {
        $cache = $this->createCache();
        $cache->set('tenant', $this->createTenant(), 1);

        // Immediately check - should still be there
        self::assertNotNull($cache->get('tenant'));

        sleep(1);

        // Now expired
        self::assertNull($cache->get('tenant'));
    }

    public function testItReturnsNullForExpiredItemAndRemovesIt(): void
    {
        $cache = $this->createCache();
        $cache->set('tenant', $this->createTenant(), 1);

        sleep(1);

        // First call returns null and removes the item
        self::assertNull($cache->get('tenant'));

        // Second call should also return null (item was removed)
        self::assertNull($cache->get('tenant'));
    }

    public function testItStoresWithZeroTtlAsNeverExpire(): void
    {
        $cache = $this->createCache();
        $tenant = $this->createTenant();

        $cache->set('tenant', $tenant, 0);

        // Should still be there (no expiration)
        self::assertEquals($tenant, $cache->get('tenant'));
    }

    public function testItReturnsNullForCorruptedEncryptedData(): void
    {
        $encryptionService = new EncryptionService(EncryptionService::generateKey());
        $cache = new ArrayCache($encryptionService);
        $tenant = $this->createTenant();

        $cache->set('tenant', $tenant, 3600);

        // Tamper with the internal storage by accessing reflection
        $reflection = new \ReflectionClass($cache);
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($cache);

        // Corrupt the encrypted data
        $items['tenant']['tenant'] = 'corrupted-data';
        $itemsProperty->setValue($cache, $items);

        // Should return null due to decryption failure
        self::assertNull($cache->get('tenant'));
    }

    public function testItReturnsNullForUnserializableData(): void
    {
        $encryptionService = new EncryptionService(EncryptionService::generateKey());
        $cache = new ArrayCache($encryptionService);

        // Manually set invalid serialized data
        $reflection = new \ReflectionClass($cache);
        $itemsProperty = $reflection->getProperty('items');
        $itemsProperty->setAccessible(true);

        // Encrypt something that's not a valid Tenant serialization
        $invalidData = $encryptionService->encrypt('not-a-serialized-tenant');
        $itemsProperty->setValue($cache, [
            'tenant' => [
                'tenant' => $invalidData,
                'expiresAt' => null,
            ],
        ]);

        // Should return null due to unserialization failure
        self::assertNull($cache->get('tenant'));
    }

    public function testItDeletesNonExistentKeyWithoutError(): void
    {
        $cache = $this->createCache();

        // Should not throw
        $cache->delete('non-existent-key');

        self::assertNull($cache->get('non-existent-key'));
    }

    public function testItStoresMultipleTenants(): void
    {
        $cache = $this->createCache();
        $tenant1 = $this->createTenant();
        $tenant2 = $this->createTenant();

        $cache->set('tenant1', $tenant1, 3600);
        $cache->set('tenant2', $tenant2, 3600);

        self::assertEquals($tenant1, $cache->get('tenant1'));
        self::assertEquals($tenant2, $cache->get('tenant2'));
    }

    public function testItOverwritesExistingKey(): void
    {
        $cache = $this->createCache();
        $tenant1 = $this->createTenant();
        $tenant2 = $this->createTenant();

        $cache->set('tenant', $tenant1, 3600);
        self::assertEquals($tenant1, $cache->get('tenant'));

        $cache->set('tenant', $tenant2, 3600);
        self::assertEquals($tenant2, $cache->get('tenant'));
    }

    private function createCache(): ArrayCache
    {
        return new ArrayCache(
            new EncryptionService(EncryptionService::generateKey()),
        );
    }
}
