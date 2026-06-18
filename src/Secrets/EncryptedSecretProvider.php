<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Secrets;

use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

/**
 * Secret provider that decrypts passwords stored in the tenant record.
 *
 * This is the recommended provider for auto-provisioned tenants where
 * the encrypted_database_password field contains an encrypted password
 * rather than an environment variable reference.
 */
final readonly class EncryptedSecretProvider implements SecretProviderInterface
{
    public function __construct(
        private EncryptionService $encryption,
    ) {
    }

    /**
     * Decrypt the tenant's database password.
     *
     * The encryptedDatabasePassword field should contain a base64-encoded
     * encrypted string created by EncryptionService::encrypt().
     *
     * @throws ConfigurationException If decryption fails
     */
    public function getDatabasePassword(Tenant $tenant): string
    {
        $encryptedPassword = $tenant->encryptedDatabasePassword;

        if ($encryptedPassword === '') {
            throw new ConfigurationException('Tenant database password is empty.');
        }

        try {
            return $this->encryption->decrypt($encryptedPassword);
        } catch (ConfigurationException $e) {
            throw new ConfigurationException(
                'Failed to decrypt tenant database password: ' . $e->getMessage(),
                $e,
            );
        }
    }
}
