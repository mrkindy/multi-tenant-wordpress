<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;

interface CacheInterface
{
    public function __construct(EncryptionService $encryptionService);

    public function get(string $key): ?Tenant;

    public function set(string $key, Tenant $tenant, int $ttlSeconds): void;

    public function delete(string $key): void;
}
