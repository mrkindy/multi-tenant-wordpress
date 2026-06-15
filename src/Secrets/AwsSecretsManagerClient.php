<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Secrets;

use Aws\Exception\AwsException;
use Aws\SecretsManager\SecretsManagerClient;
use MrKindy\MultiTenantWordPress\Contracts\AwsSecretsManagerClientInterface;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;

final readonly class AwsSecretsManagerClient implements AwsSecretsManagerClientInterface
{
    public function __construct(private SecretsManagerClient $client)
    {
    }

    public function getSecretString(string $secretId): ?string
    {
        try {
            $result = $this->client->getSecretValue([
                'SecretId' => $secretId,
            ]);
        } catch (AwsException $exception) {
            throw new ConfigurationException('Tenant database secret is unavailable.', $exception);
        }

        $secret = $result->get('SecretString');

        return is_string($secret) ? $secret : null;
    }
}
