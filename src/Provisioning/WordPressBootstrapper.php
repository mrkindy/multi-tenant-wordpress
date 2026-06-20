<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\Config\Config;
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
        private readonly string $wpPath,
        private readonly Config $config,
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

        $this->validateWpPath();
        $this->defineDatabaseConstants($tenant, $password);
        $this->defineStorageConstants($tenant);

        // Prevent WordPress from redirecting to install.php
        if (!defined('WP_INSTALLING')) {
            define('WP_INSTALLING', true);
        }

        // Load WordPress
        $wpLoadPath = $this->wpPath . '/wp-load.php';

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
    private function validateWpPath(): void
    {
        if ($this->wpPath === '') {
            throw new TenantProvisioningException(
                'WordPress path is not configured',
                'validation',
                '',
            );
        }

        if (!is_dir($this->wpPath)) {
            throw new TenantProvisioningException(
                "WordPress path does not exist: {$this->wpPath}",
                'validation',
                '',
            );
        }

        $wpLoadPath = $this->wpPath . '/wp-load.php';
        if (!file_exists($wpLoadPath)) {
            throw new TenantProvisioningException(
                "Invalid WordPress path - wp-load.php not found: {$wpLoadPath}",
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

    /**
     * Define storage constants based on the storage provider.
     */
    private function defineStorageConstants(Tenant $tenant): void
    {
        if ($this->config->storageProvider === Config::STORAGE_PROVIDER_DISK) {
            // Local disk storage - define UPLOADS constant like WordPress
            $uploadsPath = $this->config->storageBasePath . $tenant->storageFolder . '/uploads/';
            if (!defined('UPLOADS')) {
                define('UPLOADS', $uploadsPath);
            }
        } elseif ($this->config->storageProvider === Config::STORAGE_PROVIDER_S3) {
            // S3 storage - define constants for S3 plugins
            if (!defined('S3_UPLOADS_BUCKET')) {
                define('S3_UPLOADS_BUCKET', $this->config->s3Bucket);
            }
            if (!defined('S3_UPLOADS_REGION')) {
                define('S3_UPLOADS_REGION', $this->config->s3Region);
            }
            if (!defined('S3_UPLOADS_KEY_PREFIX')) {
                define('S3_UPLOADS_KEY_PREFIX', $tenant->storageFolder . '/uploads/');
            }

            // Optional S3 configuration
            if ($this->config->s3Endpoint !== '' && !defined('S3_UPLOADS_ENDPOINT')) {
                define('S3_UPLOADS_ENDPOINT', $this->config->s3Endpoint);
            }

            if ($this->config->s3UsePathStyle && !defined('S3_UPLOADS_USE_PATH_STYLE')) {
                define('S3_UPLOADS_USE_PATH_STYLE', true);
            }
        }
    }
}
