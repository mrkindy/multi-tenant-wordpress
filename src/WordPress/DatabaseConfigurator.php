<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\WordPress;

use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

final readonly class DatabaseConfigurator
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function configure(Tenant $tenant, string $password): void
    {
        if (
            $tenant->databaseHost === ''
            || $tenant->databaseName === ''
            || $tenant->databaseUser === ''
            || $password === ''
            || $tenant->databasePort < 1
            || $tenant->databasePort > 65535
            || preg_match('/[\x00-\x20;=]/', $tenant->databaseHost) === 1
        ) {
            throw new ConfigurationException('Tenant database configuration is incomplete.');
        }

        $host = $tenant->databaseHost;

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }

        $this->defineIfMissing('DB_NAME', $tenant->databaseName);
        $this->defineIfMissing('DB_USER', $tenant->databaseUser);
        $this->defineIfMissing('DB_PASSWORD', $password);
        $this->defineIfMissing('DB_HOST', $host . ':' . $tenant->databasePort);

        // Configure storage based on provider
        $this->configureStorage($tenant);
    }

    /**
     * Configure storage constants based on the storage provider.
     */
    private function configureStorage(Tenant $tenant): void
    {
        if ($this->config->storageProvider === Config::STORAGE_PROVIDER_DISK) {
            // Local disk storage - define UPLOADS constant like WordPress
            $uploadsPath = $this->config->storageBasePath . $tenant->storageFolder . '/uploads/';
            $this->defineIfMissing('UPLOADS', $uploadsPath);
        } elseif ($this->config->storageProvider === Config::STORAGE_PROVIDER_S3) {
            // S3 storage - define constants for S3 plugins
            $this->defineIfMissing('S3_UPLOADS_BUCKET', $this->config->s3Bucket);
            $this->defineIfMissing('S3_UPLOADS_REGION', $this->config->s3Region);
            $this->defineIfMissing('S3_UPLOADS_KEY_PREFIX', $tenant->storageFolder . '/uploads/');

            // Optional S3 configuration
            if ($this->config->s3Endpoint !== '') {
                $this->defineIfMissing('S3_UPLOADS_ENDPOINT', $this->config->s3Endpoint);
            }

            if ($this->config->s3UsePathStyle) {
                $this->defineIfMissing('S3_UPLOADS_USE_PATH_STYLE', true);
            }
        }
    }

    private function defineIfMissing(string $name, string|bool $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
