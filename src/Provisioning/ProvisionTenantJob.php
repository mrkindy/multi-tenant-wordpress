<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

/**
 * Job payload for provisioning a tenant.
 *
 * This DTO is dispatched to the queue system and contains
 * all necessary information to provision a tenant.
 */
final readonly class ProvisionTenantJob
{
    /**
     * @param string $tenantId The ID of the tenant to provision
     * @param string|null $adminUsername Optional admin username
     * @param string|null $adminEmail Optional admin email
     * @param string|null $adminPassword Optional admin password
     */
    public function __construct(
        public string $tenantId,
        public ?string $adminUsername = null,
        public ?string $adminEmail = null,
        public ?string $adminPassword = null,
    ) {
    }

    /**
     * Generate a secure random password if none provided.
     */
    public function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate a default admin username based on tenant ID.
     */
    public function generateUsername(string $tenantId): string
    {
        return 'admin_' . substr(md5($tenantId), 0, 8);
    }

    /**
     * Generate a default admin email based on domain.
     */
    public function generateEmail(string $domain): string
    {
        return 'admin@' . $domain;
    }
}
