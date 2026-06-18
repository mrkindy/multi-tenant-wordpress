<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\DTO;

/**
 * Data Transfer Object for WordPress administrator credentials.
 */
final readonly class ProvisioningAdminCredentials
{
    /**
     * @param string $username The WordPress admin username
     * @param string $email The WordPress admin email address
     * @param string $password The WordPress admin password (plaintext for initial setup)
     */
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
    ) {
    }
}
