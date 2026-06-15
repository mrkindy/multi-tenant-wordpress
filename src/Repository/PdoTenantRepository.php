<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Repository;

use JsonException;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use PDO;
use PDOStatement;

final readonly class PdoTenantRepository implements TenantRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByDomain(string $domain): ?Tenant
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
            SELECT
                id,
                domain,
                database_host,
                database_port,
                database_name,
                database_user,
                encrypted_database_password,
                status,
                plan,
                metadata
            FROM tenants
            WHERE domain = :domain
            LIMIT 1
            SQL,
        );

        if (!$statement instanceof PDOStatement) {
            throw new ConfigurationException('Tenant lookup could not be prepared.');
        }

        $statement->execute(['domain' => $domain]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Tenant
    {
        $requiredStringFields = [
            'id',
            'domain',
            'database_host',
            'database_name',
            'database_user',
            'encrypted_database_password',
            'status',
            'plan',
        ];

        foreach ($requiredStringFields as $field) {
            if (
                !isset($row[$field])
                || !is_scalar($row[$field])
                || (string) $row[$field] === ''
            ) {
                throw new ConfigurationException('Tenant record is malformed.');
            }
        }

        $port = filter_var(
            $row['database_port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]],
        );

        if ($port === false) {
            throw new ConfigurationException('Tenant database port is invalid.');
        }

        try {
            $metadata = $this->decodeMetadata($row['metadata'] ?? null);
        } catch (JsonException $exception) {
            throw new ConfigurationException('Tenant metadata is invalid.', $exception);
        }

        return new Tenant(
            id: (string) $row['id'],
            domain: (string) $row['domain'],
            databaseHost: (string) $row['database_host'],
            databasePort: $port,
            databaseName: (string) $row['database_name'],
            databaseUser: (string) $row['database_user'],
            encryptedDatabasePassword: (string) $row['encrypted_database_password'],
            status: strtolower((string) $row['status']),
            plan: (string) $row['plan'],
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decodeMetadata(mixed $metadata): array
    {
        if ($metadata === null || $metadata === '') {
            return [];
        }

        if (!is_string($metadata)) {
            throw new ConfigurationException('Tenant metadata is invalid.');
        }

        if (!str_starts_with(ltrim($metadata), '{')) {
            throw new ConfigurationException('Tenant metadata must be a JSON object.');
        }

        $decoded = json_decode($metadata, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new ConfigurationException('Tenant metadata must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
