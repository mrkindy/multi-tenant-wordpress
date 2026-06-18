<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

/**
 * Additional data seeder - stub implementation.
 *
 * This class is a placeholder for future additional integration.
 * Currently performs no operations but maintains the interface
 * for dependency injection and future expansion.
 */
readonly class AdditionalSeeder
{
    public function __construct(
        private WordPressBootstrapper $bootstrapper,
    ) {
    }

    /**
     * Seed additional data for the tenant.
     *
     * Currently a no-op stub. Future implementation will:
     * - Create additional pages
     * - Configure additional settings
     * - Set up default content
     * - Configure tax and shipping zones
     *
     * @param Tenant $tenant The tenant being provisioned
     * @param string $password The decrypted database password
     */
    public function seed(Tenant $tenant, string $password): void
    {
        // Bootstrap WordPress (validates the environment)
        $this->bootstrapper->bootstrap($tenant, $password);

        // Stub: WooCommerce seeding not yet implemented
        // This method is intentionally empty for future expansion
    }
}
