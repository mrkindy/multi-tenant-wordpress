<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\DTO\UpdateTenant;

interface TenantProvisioningRepositoryInterface
{
    public function create(CreateTenant $tenant): Tenant;

    public function update(UpdateTenant $tenant): ?Tenant;

    public function delete(string $id): bool;
}
