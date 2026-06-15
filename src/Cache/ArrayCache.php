<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Cache;

use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;

final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{tenant: Tenant, expiresAt: int|null}>
     */
    private array $items = [];

    public function get(string $key): ?Tenant
    {
        if (!isset($this->items[$key])) {
            return null;
        }

        $item = $this->items[$key];

        if ($item['expiresAt'] !== null && $item['expiresAt'] <= time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['tenant'];
    }

    public function set(string $key, Tenant $tenant, int $ttlSeconds): void
    {
        $this->items[$key] = [
            'tenant' => $tenant,
            'expiresAt' => $ttlSeconds === 0 ? null : time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }
}
