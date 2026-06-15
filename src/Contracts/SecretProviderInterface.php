<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

interface SecretProviderInterface
{
    public function getDatabasePassword(Tenant $tenant): string;
}
