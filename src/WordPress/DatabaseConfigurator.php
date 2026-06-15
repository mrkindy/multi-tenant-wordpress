<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\WordPress;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

final readonly class DatabaseConfigurator
{
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
    }

    private function defineIfMissing(string $name, string $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
