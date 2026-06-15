<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Cache;

use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;

final class ArrayCache implements CacheInterface
{
    /**
     * @var array<string, array{tenant: string, expiresAt: int|null}>
     */
    private array $items = [];

    public function __construct(
        private readonly EncryptionService $encryptionService,
    ) {
    }

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

        try {
            $decrypted = $this->encryptionService->decrypt($item['tenant']);
            $tenant = unserialize($decrypted, ['allowed_classes' => [Tenant::class]]);

            return $tenant instanceof Tenant ? $tenant : null;
        } catch (\Throwable) {
            //(Cache miss)
            return null;
        }
    }

    public function set(string $key, Tenant $tenant, int $ttlSeconds): void
    {
        $serialized = serialize($tenant);
        $encrypted = $this->encryptionService->encrypt($serialized);

        $this->items[$key] = [
            'tenant' => $encrypted,
            'expiresAt' => $ttlSeconds === 0 ? null : time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }
}
