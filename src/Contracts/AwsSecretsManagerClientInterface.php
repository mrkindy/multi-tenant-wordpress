<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

interface AwsSecretsManagerClientInterface
{
    public function getSecretString(string $secretId): ?string;
}
