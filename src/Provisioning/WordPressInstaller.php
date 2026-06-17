<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;

/**
 * Installs WordPress schema using WordPress Core APIs.
 *
 * Uses wp-admin/includes/schema.php and wp-admin/includes/upgrade.php
 * to ensure compatibility with future WordPress versions.
 */
readonly class WordPressInstaller
{
    public function __construct(
        private WordPressBootstrapper $bootstrapper,
    ) {
    }

    /**
     * Install WordPress schema for the tenant.
     *
     * This method is idempotent - running it multiple times is safe
     * due to dbDelta's table checking.
     *
     * @param Tenant $tenant The tenant to install for
     * @param string $password The decrypted database password
     * @throws TenantProvisioningException If installation fails
     */
    public function install(Tenant $tenant, string $password): void
    {
        // Bootstrap WordPress first
        $this->bootstrapper->bootstrap($tenant, $password);

        // Load required WordPress admin files
        $this->loadWordPressAdminFiles();

        try {
            // Get schema from WordPress core
            $schema = wp_get_db_schema('all');

            if ($schema === '' || $schema === '0') {
                throw new TenantProvisioningException(
                    'WordPress schema is empty',
                    'schema_generation',
                    $tenant->id,
                );
            }

            // Execute schema using dbDelta (creates tables if they don't exist)
            $result = dbDelta($schema);

            // Populate default options (idempotent - skips existing keys)
            populate_options();

            // Populate roles and capabilities (idempotent)
            populate_roles();
        } catch (TenantProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TenantProvisioningException(
                "WordPress installation failed: {$e->getMessage()}",
                'wordpress_installation',
                $tenant->id,
                $e,
            );
        }
    }

    /**
     * Load WordPress admin files required for installation.
     *
     * @throws TenantProvisioningException If files cannot be loaded
     */
    private function loadWordPressAdminFiles(): void
    {
        $schemaFile = ABSPATH . 'wp-admin/includes/schema.php';
        $upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';

        if (!file_exists($schemaFile)) {
            throw new TenantProvisioningException(
                "WordPress schema file not found: {$schemaFile}",
                'file_loading',
                '',
            );
        }

        if (!file_exists($upgradeFile)) {
            throw new TenantProvisioningException(
                "WordPress upgrade file not found: {$upgradeFile}",
                'file_loading',
                '',
            );
        }

        require_once $schemaFile;
        require_once $upgradeFile;
    }
}
