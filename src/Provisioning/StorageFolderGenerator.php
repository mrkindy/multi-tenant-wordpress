<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\StorageFolderGeneratorInterface;

/**
 * Generates unique storage folder names for tenants.
 *
 * Creates folder names that are:
 * - Unique per tenant
 * - Hard to guess (includes random suffix)
 * - Include tenant ID for easy identification
 *
 * Format: tenant_{id}_{random_suffix}
 * Example: tenant_41_a7x9k2m8pQ3LwRtZvBnJy
 */
final readonly class StorageFolderGenerator implements StorageFolderGeneratorInterface
{
    private const PREFIX = 'tenant';
    private const RANDOM_LENGTH = 20;

    public function generate(string $tenantId): string
    {
        $randomSuffix = $this->generateRandomSuffix();

        return sprintf(
            '%s_%s_%s',
            self::PREFIX,
            $tenantId,
            $randomSuffix,
        );
    }

    /**
     * Generate a cryptographically secure random suffix.
     *
     * Uses alphanumeric characters (uppercase, lowercase, and digits).
     */
    private function generateRandomSuffix(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
