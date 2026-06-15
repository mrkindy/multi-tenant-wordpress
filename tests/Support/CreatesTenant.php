<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Support;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

trait CreatesTenant
{
    private function createTenant(
        string $status = 'active',
        string $secretReference = 'TENANT_DATABASE_PASSWORD',
    ): Tenant {
        return new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: $secretReference,
            status: $status,
            plan: 'business',
            metadata: ['region' => 'eu-central-1'],
        );
    }
}
