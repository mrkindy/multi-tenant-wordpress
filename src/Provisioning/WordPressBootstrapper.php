<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;

/**
 * Bootstraps the existing Bedrock/WordPress installation for tenant provisioning.
 *
 * This class loads WordPress core APIs without shell execution,
 * using the shared Bedrock codebase.
 */
final class WordPressBootstrapper
{
    private static bool $bootstrapped = false;

    public function __construct(
        private readonly string $bedrockPath,
    ) {
    }

    /**
     * Bootstrap WordPress for the given tenant.
     *
     * @param Tenant $tenant The tenant to bootstrap for
     * @param string $password The decrypted database password
     * @throws TenantProvisioningException If bootstrap fails
     */
    public function bootstrap(Tenant $tenant, string $password): void
    {
        if (self::$bootstrapped) {
            return;
        }

        $this->validateBedrockPath();
        $this->defineDatabaseConstants($tenant, $password);

        // Prevent WordPress from redirecting to install.php
        if (!defined('WP_INSTALLING')) {
            define('WP_INSTALLING', true);
        }

        // Load WordPress
        $wpLoadPath = $this->bedrockPath . '/web/wp/wp-load.php';

        if (!file_exists($wpLoadPath)) {
            throw new TenantProvisioningException(
                "WordPress load file not found: {$wpLoadPath}",
                'wordpress_bootstrap',
                $tenant->id,
            );
        }

        try {
            require_once $wpLoadPath;
            self::$bootstrapped = true;
        } catch (\Throwable $e) {
            throw new TenantProvisioningException(
                "Failed to bootstrap WordPress: {$e->getMessage()}",
                'wordpress_bootstrap',
                $tenant->id,
                $e,
            );
        }
    }

    /**
     * Reset bootstrap state (for testing).
     */
    public static function reset(): void
    {
        self::$bootstrapped = false;
    }

    /**
     * Validate that the Bedrock path exists and contains WordPress.
     *
     * @throws TenantProvisioningException If path is invalid
     */
    private function validateBedrockPath(): void
    {
        if ($this->bedrockPath === '') {
            throw new TenantProvisioningException(
                'Bedrock path is not configured',
                'validation',
                '',
            );
        }

        if (!is_dir($this->bedrockPath)) {
            throw new TenantProvisioningException(
                "Bedrock path does not exist: {$this->bedrockPath}",
                'validation',
                '',
            );
        }

        $wpLoadPath = $this->bedrockPath . '/web/wp/wp-load.php';
        if (!file_exists($wpLoadPath)) {
            throw new TenantProvisioningException(
                "Invalid Bedrock path - wp-load.php not found: {$wpLoadPath}",
                'validation',
                '',
            );
        }
    }

    /**
     * Define WordPress database constants before loading WordPress.
     */
    private function defineDatabaseConstants(Tenant $tenant, string $password): void
    {
        $host = $tenant->databaseHost;

        // Handle IPv6 addresses
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        $dbHost = $host . ':' . $tenant->databasePort;

        // Only define if not already defined
        if (!defined('DB_NAME')) {
            define('DB_NAME', $tenant->databaseName);
        }
        if (!defined('DB_USER')) {
            define('DB_USER', $tenant->databaseUser);
        }
        if (!defined('DB_PASSWORD')) {
            define('DB_PASSWORD', $password);
        }
        if (!defined('DB_HOST')) {
            define('DB_HOST', $dbHost);
        }

        // Set table prefix if not defined
        if (!defined('DB_CHARSET')) {
            define('DB_CHARSET', 'utf8mb4');
        }
        if (!defined('DB_COLLATE')) {
            define('DB_COLLATE', 'utf8mb4_unicode_ci');
        }
    }
}
