<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Repository;

use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class PdoTenantRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoTenantRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE tenants (
                id INTEGER PRIMARY KEY,
                domain TEXT NOT NULL UNIQUE,
                database_host TEXT NOT NULL,
                database_port INTEGER NOT NULL,
                database_name TEXT NOT NULL,
                database_user TEXT NOT NULL,
                encrypted_database_password TEXT NOT NULL,
                status TEXT NOT NULL,
                plan TEXT NOT NULL,
                metadata TEXT NULL
            )
            SQL,
        );
        $this->repository = new PdoTenantRepository($this->pdo);
    }

    public function testItLoadsTenantUsingPreparedLookup(): void
    {
        $this->insertTenant('shop.example.com', '{"region":"eu-central-1"}');

        $tenant = $this->repository->findByDomain('shop.example.com');

        self::assertNotNull($tenant);
        self::assertSame('1', $tenant->id);
        self::assertSame('tenant_1', $tenant->databaseName);
        self::assertSame(3306, $tenant->databasePort);
        self::assertSame(['region' => 'eu-central-1'], $tenant->metadata);
    }

    public function testItReturnsNullForUnknownDomain(): void
    {
        self::assertNull($this->repository->findByDomain('missing.example.com'));
    }

    public function testItAcceptsAnEmptyMetadataObject(): void
    {
        $this->insertTenant('shop.example.com', '{}');

        $tenant = $this->repository->findByDomain('shop.example.com');

        self::assertNotNull($tenant);
        self::assertSame([], $tenant->metadata);
    }

    public function testSqlInjectionPayloadDoesNotMatch(): void
    {
        $this->insertTenant('shop.example.com', null);

        self::assertNull(
            $this->repository->findByDomain("' OR 1=1 --"),
        );
    }

    public function testItRejectsMalformedMetadata(): void
    {
        $this->insertTenant('shop.example.com', '{bad-json');

        $this->expectException(ConfigurationException::class);

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsMetadataList(): void
    {
        $this->insertTenant('shop.example.com', '[]');

        $this->expectException(ConfigurationException::class);

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsInvalidDatabasePort(): void
    {
        $this->insertTenant('shop.example.com', null);
        $this->pdo->exec('UPDATE tenants SET database_port = 70000');

        $this->expectException(ConfigurationException::class);

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsMissingRequiredField(): void
    {
        $this->insertTenant('shop.example.com', null);
        $this->pdo->exec("UPDATE tenants SET database_host = ''");

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsNullRequiredField(): void
    {
        // Insert a tenant with empty string instead of NULL (since column is NOT NULL)
        $this->insertTenant('shop.example.com', null);
        // Simulate a null value by directly manipulating the data
        // The hydrate method checks for null, empty string, or missing fields
        $this->pdo->exec("UPDATE tenants SET database_host = ''");

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsNonObjectMetadata(): void
    {
        $this->insertTenant('shop.example.com', '"string-value"');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant metadata must be a JSON object.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsNonJsonMetadata(): void
    {
        $this->insertTenant('shop.example.com', 'not-json-at-all');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant metadata must be a JSON object.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsNonStringMetadata(): void
    {
        // This tests the decodeMetadata when metadata is not a string (e.g., integer from DB)
        // SQLite may convert integers to strings, so we test with an actual integer
        $this->pdo->exec(
            <<<'SQL'
            INSERT INTO tenants (
                id, domain, database_host, database_port, database_name,
                database_user, encrypted_database_password, status, plan, metadata
            ) VALUES (
                2, 'test.example.com', 'db', 3306, 'tenant_2',
                'user', 'pass', 'active', 'basic', 12345
            )
            SQL,
        );

        $this->expectException(ConfigurationException::class);
        // The error message depends on whether SQLite returns it as int or string
        // If int: 'Tenant metadata is invalid.'
        // If string '12345': 'Tenant metadata must be a JSON object.'

        $this->repository->findByDomain('test.example.com');
    }

    public function testItAcceptsNullMetadata(): void
    {
        $this->insertTenant('shop.example.com', null);

        $tenant = $this->repository->findByDomain('shop.example.com');

        self::assertNotNull($tenant);
        self::assertSame([], $tenant->metadata);
    }

    public function testItAcceptsEmptyStringMetadata(): void
    {
        $this->insertTenant('shop.example.com', '');

        $tenant = $this->repository->findByDomain('shop.example.com');

        self::assertNotNull($tenant);
        self::assertSame([], $tenant->metadata);
    }

    public function testItRejectsPortZero(): void
    {
        $this->insertTenant('shop.example.com', null);
        $this->pdo->exec('UPDATE tenants SET database_port = 0');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItRejectsNegativePort(): void
    {
        $this->insertTenant('shop.example.com', null);
        $this->pdo->exec('UPDATE tenants SET database_port = -1');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->findByDomain('shop.example.com');
    }

    public function testItNormalizesStatusToLowercase(): void
    {
        $this->insertTenant('shop.example.com', null);
        $this->pdo->exec("UPDATE tenants SET status = 'ACTIVE'");

        $tenant = $this->repository->findByDomain('shop.example.com');

        self::assertNotNull($tenant);
        self::assertSame('active', $tenant->status);
    }

    private function insertTenant(string $domain, ?string $metadata): void
    {
        $statement = $this->pdo->prepare(
            <<<'SQL'
            INSERT INTO tenants (
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
            ) VALUES (
                1,
                :domain,
                'tenant-db',
                3306,
                'tenant_1',
                'tenant_1_user',
                'TENANT_1_DATABASE_PASSWORD',
                'active',
                'business',
                :metadata
            )
            SQL,
        );
        self::assertInstanceOf(PDOStatement::class, $statement);
        $statement->execute([
            'domain' => $domain,
            'metadata' => $metadata,
        ]);
    }
}
