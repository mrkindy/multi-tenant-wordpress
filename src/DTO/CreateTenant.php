<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\DTO;

final readonly class CreateTenant
{
    /**
     * @param array<array-key, mixed> $metadata
     */
    public function __construct(
        public string $domain,
        public string $databaseHost,
        public int $databasePort,
        public string $databaseName,
        public string $databaseUser,
        public string $encryptedDatabasePassword,
        public string $status = 'active',
        public string $plan = 'basic',
        public array $metadata = [],
    ) {
    }
}
