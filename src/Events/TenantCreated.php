<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Events;

use MrKindy\MultiTenantWordPress\DTO\Tenant;

/**
 * Event dispatched when a new tenant is created.
 *
 * This event triggers the automated provisioning process.
 */
final readonly class TenantCreated
{
    /**
     * @param Tenant $tenant The newly created tenant
     * @param string|null $adminUsername Optional admin username (auto-generated if null)
     * @param string|null $adminEmail Optional admin email (derived from domain if null)
     * @param string|null $adminPassword Optional admin password (auto-generated if null)
     */
    public function __construct(
        public Tenant $tenant,
        public ?string $adminUsername = null,
        public ?string $adminEmail = null,
        public ?string $adminPassword = null,
    ) {
    }
}
