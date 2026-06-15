<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

interface TenantRepositoryInterface
{
    public function findByDomain(string $domain): ?Tenant;
}
