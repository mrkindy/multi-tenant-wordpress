<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Secrets;

use JsonException;
use MrKindy\MultiTenantWordPress\Contracts\AwsSecretsManagerClientInterface;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

final readonly class AwsSecretsProvider implements SecretProviderInterface
{
    public function __construct(
        private AwsSecretsManagerClientInterface $client,
        private string $passwordKey = 'password',
    ) {
    }

    /**
     * The tenant's encryptedDatabasePassword value is the AWS secret ID or ARN.
     */
    public function getDatabasePassword(Tenant $tenant): string
    {
        $secret = $this->client->getSecretString(
            $tenant->encryptedDatabasePassword,
        );

        if (!is_string($secret) || $secret === '') {
            throw new ConfigurationException('Tenant database secret is unavailable.');
        }

        if (!str_starts_with(ltrim($secret), '{')) {
            return $secret;
        }

        try {
            $payload = json_decode($secret, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ConfigurationException('Tenant database secret is malformed.', $exception);
        }

        $password = is_array($payload) ? ($payload[$this->passwordKey] ?? null) : null;

        if (!is_string($password) || $password === '') {
            throw new ConfigurationException('Tenant database password is unavailable.');
        }

        return $password;
    }
}
