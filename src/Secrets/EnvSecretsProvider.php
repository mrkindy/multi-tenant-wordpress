<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Secrets;

use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

final readonly class EnvSecretsProvider implements SecretProviderInterface
{
    /**
     * The tenant's encryptedDatabasePassword value is the environment variable name.
     */
    public function getDatabasePassword(Tenant $tenant): string
    {
        $reference = $tenant->encryptedDatabasePassword;

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $reference) !== 1) {
            throw new ConfigurationException('Environment secret reference is invalid.');
        }

        $password = getenv($reference);

        if ($password === false || $password === '') {
            throw new ConfigurationException('Tenant database secret is unavailable.');
        }

        return $password;
    }
}
