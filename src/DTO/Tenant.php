<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\DTO;

final readonly class Tenant
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $id,
        public string $domain,
        public string $databaseHost,
        public int $databasePort,
        public string $databaseName,
        public string $databaseUser,
        public string $encryptedDatabasePassword,
        public string $status,
        public string $plan,
        public array $metadata = [],
    ) {
    }
}
