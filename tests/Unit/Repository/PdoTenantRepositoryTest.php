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
