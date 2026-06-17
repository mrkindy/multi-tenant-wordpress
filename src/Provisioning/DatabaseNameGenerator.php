<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\DatabaseNameGeneratorInterface;

/**
 * Generates tenant database names and usernames with configurable prefixes.
 *
 * Ensures generated identifiers are valid for MySQL/MariaDB:
 * - Database names: max 64 characters
 * - Usernames: max 32 characters (MySQL 5.7), 80+ (MySQL 8.0+)
 */
final readonly class DatabaseNameGenerator implements DatabaseNameGeneratorInterface
{
    private const MAX_DB_NAME_LENGTH = 64;
    private const MAX_DB_USER_LENGTH = 32;

    public function __construct(
        private string $databaseNamePrefix = 'tenant_',
        private string $databaseUserPrefix = 'tenant_',
    ) {
    }

    public function generateDatabaseName(string $tenantId, string $domain): string
    {
        // Sanitize tenant ID: alphanumeric and underscore only
        $sanitizedId = preg_replace('/[^a-zA-Z0-9_]/', '_', $tenantId);

        $name = $this->databaseNamePrefix . $sanitizedId;

        // Truncate if exceeds max length
        if (strlen($name) > self::MAX_DB_NAME_LENGTH) {
            $name = substr($name, 0, self::MAX_DB_NAME_LENGTH);
        }

        return $name;
    }

    public function generateDatabaseUser(string $tenantId, string $domain): string
    {
        // Sanitize tenant ID: alphanumeric and underscore only
        $sanitizedId = preg_replace('/[^a-zA-Z0-9_]/', '_', $tenantId);

        $user = $this->databaseUserPrefix . $sanitizedId . '_user';

        // Truncate if exceeds max length
        if (strlen($user) > self::MAX_DB_USER_LENGTH) {
            // Keep the suffix '_user' if possible
            $suffix = '_user';
            $maxPrefixLength = self::MAX_DB_USER_LENGTH - strlen($suffix);
            $user = substr($this->databaseUserPrefix . $sanitizedId, 0, $maxPrefixLength) . $suffix;
        }

        return $user;
    }
}
