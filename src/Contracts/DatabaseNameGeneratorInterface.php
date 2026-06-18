<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

/**
 * Interface for generating tenant database names and usernames.
 *
 * Implementations must ensure generated identifiers are valid
 * for MySQL/MariaDB (max 64 chars for database names, 32 for usernames).
 */
interface DatabaseNameGeneratorInterface
{
    /**
     * Generate a database name for a tenant.
     *
     * @param string $tenantId The unique tenant identifier
     * @param string $domain The tenant's domain (for reference)
     */
    public function generateDatabaseName(string $tenantId, string $domain): string;

    /**
     * Generate a database username for a tenant.
     *
     * @param string $tenantId The unique tenant identifier
     * @param string $domain The tenant's domain (for reference)
     */
    public function generateDatabaseUser(string $tenantId, string $domain): string;
}
