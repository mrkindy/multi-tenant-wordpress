<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

interface CacheInterface
{
    public function get(string $key): ?Tenant;

    public function set(string $key, Tenant $tenant, int $ttlSeconds): void;

    public function delete(string $key): void;
}
