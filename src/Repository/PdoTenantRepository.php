<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Repository;

use JsonException;
use MrKindy\MultiTenantWordPress\Contracts\TenantProvisioningRepositoryInterface;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\DTO\UpdateTenant;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use PDO;
use PDOStatement;

final readonly class PdoTenantRepository implements TenantRepositoryInterface, TenantProvisioningRepositoryInterface
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

    public function create(CreateTenant $tenant): Tenant
    {
        $this->assertTenantCanBeSaved($tenant);
        $metadata = $this->metadataAsObject($tenant->metadata);

        try {
            $encodedMetadata = $this->encodeMetadata($metadata);
        } catch (JsonException $exception) {
            throw new ConfigurationException('Tenant metadata is invalid.', $exception);
        }

        $statement = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO tenants (
                domain,
                database_host,
                database_port,
                database_name,
                database_user,
                encrypted_database_password,
                status,
                plan,
                metadata
            ) VALUES (
                :domain,
                :database_host,
                :database_port,
                :database_name,
                :database_user,
                :encrypted_database_password,
                :status,
                :plan,
                :metadata
            )
            SQL,
        );

        if (!$statement instanceof PDOStatement) {
            throw new ConfigurationException('Tenant creation could not be prepared.');
        }

        $statement->execute([
            'domain' => $tenant->domain,
            'database_host' => $tenant->databaseHost,
            'database_port' => $tenant->databasePort,
            'database_name' => $tenant->databaseName,
            'database_user' => $tenant->databaseUser,
            'encrypted_database_password' => $tenant->encryptedDatabasePassword,
            'status' => strtolower($tenant->status),
            'plan' => $tenant->plan,
            'metadata' => $encodedMetadata,
        ]);

        $id = $this->pdo->lastInsertId();

        if ($id === false) {
            throw new ConfigurationException('Created tenant ID is unavailable.');
        }

        return new Tenant(
            id: $id,
            domain: $tenant->domain,
            databaseHost: $tenant->databaseHost,
            databasePort: $tenant->databasePort,
            databaseName: $tenant->databaseName,
            databaseUser: $tenant->databaseUser,
            encryptedDatabasePassword: $tenant->encryptedDatabasePassword,
            status: strtolower($tenant->status),
            plan: $tenant->plan,
            metadata: $metadata,
        );
    }

    public function update(UpdateTenant $tenant): ?Tenant
    {
        if ($tenant->id === '') {
            throw new ConfigurationException('Tenant record is malformed.');
        }

        $this->assertTenantCanBeSaved($tenant);
        $metadata = $this->metadataAsObject($tenant->metadata);

        try {
            $encodedMetadata = $this->encodeMetadata($metadata);
        } catch (JsonException $exception) {
            throw new ConfigurationException('Tenant metadata is invalid.', $exception);
        }

        $statement = $this->pdo->prepare(
            <<<'SQL'
            UPDATE tenants
            SET
                domain = :domain,
                database_host = :database_host,
                database_port = :database_port,
                database_name = :database_name,
                database_user = :database_user,
                encrypted_database_password = :encrypted_database_password,
                status = :status,
                plan = :plan,
                metadata = :metadata
            WHERE id = :id
            SQL,
        );

        if (!$statement instanceof PDOStatement) {
            throw new ConfigurationException('Tenant update could not be prepared.');
        }

        $statement->execute([
            'id' => $tenant->id,
            'domain' => $tenant->domain,
            'database_host' => $tenant->databaseHost,
            'database_port' => $tenant->databasePort,
            'database_name' => $tenant->databaseName,
            'database_user' => $tenant->databaseUser,
            'encrypted_database_password' => $tenant->encryptedDatabasePassword,
            'status' => strtolower($tenant->status),
            'plan' => $tenant->plan,
            'metadata' => $encodedMetadata,
        ]);

        return $this->findById($tenant->id);
    }

    public function delete(string $id): bool
    {
        if ($id === '') {
            throw new ConfigurationException('Tenant record is malformed.');
        }

        $statement = $this->pdo->prepare(
            <<<'SQL'
            DELETE FROM tenants
            WHERE id = :id
            SQL,
        );

        if (!$statement instanceof PDOStatement) {
            throw new ConfigurationException('Tenant deletion could not be prepared.');
        }

        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
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

    private function findById(string $id): ?Tenant
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
            WHERE id = :id
            LIMIT 1
            SQL,
        );

        if (!$statement instanceof PDOStatement) {
            throw new ConfigurationException('Tenant lookup could not be prepared.');
        }

        $statement->execute(['id' => $id]);

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    private function assertTenantCanBeSaved(CreateTenant|UpdateTenant $tenant): void
    {
        $requiredStrings = [
            $tenant->domain,
            $tenant->databaseHost,
            $tenant->databaseName,
            $tenant->databaseUser,
            $tenant->encryptedDatabasePassword,
            $tenant->status,
            $tenant->plan,
        ];

        foreach ($requiredStrings as $value) {
            if ($value === '') {
                throw new ConfigurationException('Tenant record is malformed.');
            }
        }

        if ($tenant->databasePort < 1 || $tenant->databasePort > 65535) {
            throw new ConfigurationException('Tenant database port is invalid.');
        }
    }

    /**
     * @param array<array-key, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private function metadataAsObject(array $metadata): array
    {
        $object = [];

        foreach ($metadata as $key => $value) {
            if (!is_string($key)) {
                throw new ConfigurationException('Tenant metadata must be a JSON object.');
            }

            $object[$key] = $value;
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws JsonException
     */
    private function encodeMetadata(array $metadata): string
    {
        if ($metadata === []) {
            return '{}';
        }

        return json_encode($metadata, JSON_THROW_ON_ERROR);
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
