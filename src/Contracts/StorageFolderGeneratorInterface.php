<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

/**
 * Interface for generating unique storage folder names for tenants.
 *
 * The generated folder names should be unique, hard to guess,
 * and include the tenant ID for easy identification.
 *
 * Example: tenant_41_a7x9k2m8pQ3LwRtZvBnJy
 */
interface StorageFolderGeneratorInterface
{
    /**
     * Generate a unique storage folder name for a tenant.
     *
     * @param string $tenantId The tenant ID
     * @return string The generated folder name (e.g., "tenant_41_a7x9k2m8pQ3LwRtZvBnJy")
     */
    public function generate(string $tenantId): string;
}
