<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Resolver;

use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantNotFoundException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantSuspendedException;

final readonly class TenantResolver
{
    public function __construct(
        private TenantRepositoryInterface $repository,
        private CacheInterface $cache,
        private int $cacheTtlSeconds = 60,
    ) {
    }

    public function resolve(string $domain): Tenant
    {
        $cacheKey = 'tenant:' . hash('sha256', $domain);
        $tenant = $this->cache->get($cacheKey);

        if ($tenant === null) {
            $tenant = $this->repository->findByDomain($domain);

            if ($tenant === null) {
                throw new TenantNotFoundException();
            }

            $this->cache->set($cacheKey, $tenant, $this->cacheTtlSeconds);
        }

        if ($tenant->status !== 'active') {
            throw new TenantSuspendedException();
        }

        return $tenant;
    }
}
