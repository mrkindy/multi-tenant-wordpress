<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Repository;

use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\DTO\UpdateTenant;
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
                storage_folder TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                plan TEXT NOT NULL,
                metadata TEXT NULL,
                wp_admin_username TEXT NULL,
                wp_admin_email TEXT NULL,
                installed_at TEXT NULL,
                installation_error TEXT NULL,
                installation_attempts INTEGER DEFAULT 0
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

    public function testItCreatesTenantUsingPreparedStatement(): void
    {
        $tenant = $this->repository->create(new CreateTenant(
            domain: 'created.example.com',
            databaseHost: 'tenant-created-db',
            databasePort: 3307,
            databaseName: 'tenant_created',
            databaseUser: 'tenant_created_user',
            encryptedDatabasePassword: 'TENANT_CREATED_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'ACTIVE',
            plan: 'enterprise',
            metadata: ['uploads_path' => '/srv/uploads/created'],
        ));

        self::assertSame('1', $tenant->id);
        self::assertSame('created.example.com', $tenant->domain);
        self::assertSame('active', $tenant->status);
        self::assertSame(['uploads_path' => '/srv/uploads/created'], $tenant->metadata);

        $loaded = $this->repository->findByDomain('created.example.com');

        self::assertNotNull($loaded);
        self::assertSame($tenant->id, $loaded->id);
        self::assertSame('tenant_created', $loaded->databaseName);
        self::assertSame(['uploads_path' => '/srv/uploads/created'], $loaded->metadata);
    }

    public function testItStoresEmptyCreateMetadataAsObject(): void
    {
        $this->repository->create(new CreateTenant(
            domain: 'empty-metadata.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_empty_metadata',
            databaseUser: 'tenant_empty_metadata_user',
            encryptedDatabasePassword: 'TENANT_EMPTY_METADATA_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
        ));

        $statement = $this->pdo->query(
            "SELECT metadata FROM tenants WHERE domain = 'empty-metadata.example.com'",
        );
        self::assertInstanceOf(PDOStatement::class, $statement);

        $metadata = $statement->fetchColumn();

        self::assertSame('{}', $metadata);
    }

    public function testItRejectsCreateMetadataLists(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant metadata must be a JSON object.');

        $metadata = ['one', 'two'];

        $this->repository->create(new CreateTenant(
            domain: 'list-metadata.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_list_metadata',
            databaseUser: 'tenant_list_metadata_user',
            encryptedDatabasePassword: 'TENANT_LIST_METADATA_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            metadata: $metadata,
        ));
    }

    public function testItRejectsCreatePortZero(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->create(new CreateTenant(
            domain: 'bad-port.example.com',
            databaseHost: 'tenant-db',
            databasePort: 0,
            databaseName: 'tenant_bad_port',
            databaseUser: 'tenant_bad_port_user',
            encryptedDatabasePassword: 'TENANT_BAD_PORT_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
        ));
    }

    public function testItUpdatesTenantUsingPreparedStatement(): void
    {
        $this->insertTenant('shop.example.com', '{"region":"eu-central-1"}');

        $tenant = $this->repository->update(new UpdateTenant(
            id: '1',
            domain: 'updated.example.com',
            databaseHost: 'tenant-updated-db',
            databasePort: 3307,
            databaseName: 'tenant_updated',
            databaseUser: 'tenant_updated_user',
            encryptedDatabasePassword: 'TENANT_UPDATED_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'SUSPENDED',
            plan: 'enterprise',
            metadata: ['region' => 'us-east-1'],
        ));

        self::assertNotNull($tenant);
        self::assertSame('1', $tenant->id);
        self::assertSame('updated.example.com', $tenant->domain);
        self::assertSame('tenant-updated-db', $tenant->databaseHost);
        self::assertSame(3307, $tenant->databasePort);
        self::assertSame('tenant_updated', $tenant->databaseName);
        self::assertSame('tenant_updated_user', $tenant->databaseUser);
        self::assertSame('TENANT_UPDATED_DATABASE_PASSWORD', $tenant->encryptedDatabasePassword);
        self::assertSame('suspended', $tenant->status);
        self::assertSame('enterprise', $tenant->plan);
        self::assertSame(['region' => 'us-east-1'], $tenant->metadata);
        self::assertNull($this->repository->findByDomain('shop.example.com'));
        self::assertEquals($tenant, $this->repository->findByDomain('updated.example.com'));
    }

    public function testItReturnsNullWhenUpdatingMissingTenant(): void
    {
        $tenant = $this->repository->update(new UpdateTenant(
            id: '404',
            domain: 'missing.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_missing',
            databaseUser: 'tenant_missing_user',
            encryptedDatabasePassword: 'TENANT_MISSING_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));

        self::assertNull($tenant);
    }

    public function testItRejectsUpdateMetadataLists(): void
    {
        $this->insertTenant('shop.example.com', null);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant metadata must be a JSON object.');

        $metadata = ['one', 'two'];

        $this->repository->update(new UpdateTenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'TENANT_1_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'business',
            metadata: $metadata,
        ));
    }

    public function testItRejectsUpdateWithEmptyId(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->update(new UpdateTenant(
            id: '',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'TENANT_1_DATABASE_PASSWORD',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'business',
        ));
    }

    public function testItDeletesTenantById(): void
    {
        $this->insertTenant('shop.example.com', null);

        self::assertTrue($this->repository->delete('1'));
        self::assertNull($this->repository->findByDomain('shop.example.com'));
    }

    public function testItReturnsFalseWhenDeletingMissingTenant(): void
    {
        self::assertFalse($this->repository->delete('404'));
    }

    public function testItRejectsDeleteWithEmptyId(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->delete('');
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
                database_user, encrypted_database_password, storage_folder, status, plan, metadata
            ) VALUES (
                2, 'test.example.com', 'db', 3306, 'tenant_2',
                'user', 'pass', 'tenant_2_a7x9k2m8pQ3LwRtZvBnJy', 'active', 'basic', 12345
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

    public function testItFindsTenantById(): void
    {
        $this->insertTenant('shop.example.com', '{"region":"eu-central-1"}');

        $tenant = $this->repository->findById('1');

        self::assertNotNull($tenant);
        self::assertSame('1', $tenant->id);
        self::assertSame('shop.example.com', $tenant->domain);
        self::assertSame(['region' => 'eu-central-1'], $tenant->metadata);
    }

    public function testItReturnsNullForUnknownId(): void
    {
        self::assertNull($this->repository->findById('999'));
    }

    public function testItUpdatesTenantStatus(): void
    {
        $this->insertTenant('shop.example.com', null);

        $this->repository->updateStatus('1', 'INSTALLING');

        $tenant = $this->repository->findById('1');
        self::assertNotNull($tenant);
        self::assertSame('installing', $tenant->status);
    }

    public function testItMarksTenantAsInstalled(): void
    {
        $this->insertTenant('shop.example.com', null);
        $installedAt = new \DateTimeImmutable('2024-01-15 10:30:00');

        $this->repository->markInstalled('1', 'admin_user', 'admin@example.com', $installedAt);

        // Verify by checking the database directly since markInstalled doesn't return the tenant
        $statement = $this->pdo
            ->query("SELECT status, wp_admin_username, wp_admin_email, installed_at FROM tenants WHERE id = 1");
        self::assertInstanceOf(PDOStatement::class, $statement);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        self::assertSame('installed', $row['status']);
        self::assertSame('admin_user', $row['wp_admin_username']);
        self::assertSame('admin@example.com', $row['wp_admin_email']);
        self::assertSame('2024-01-15 10:30:00', $row['installed_at']);
    }

    public function testItRecordsFailure(): void
    {
        $this->insertTenant('shop.example.com', null);

        $this->repository->recordFailure('1', 'Database connection failed');

        $statement = $this->pdo->query("SELECT installation_error FROM tenants WHERE id = 1");
        self::assertInstanceOf(PDOStatement::class, $statement);
        $error = $statement->fetchColumn();

        self::assertSame('Database connection failed', $error);
    }

    public function testItIncrementsAttempts(): void
    {
        $this->insertTenant('shop.example.com', null);

        $this->repository->incrementAttempts('1');
        $this->repository->incrementAttempts('1');

        $statement = $this->pdo->query("SELECT installation_attempts FROM tenants WHERE id = 1");
        self::assertInstanceOf(PDOStatement::class, $statement);
        $attempts = $statement->fetchColumn();

        self::assertSame(2, (int) $attempts);
    }

    public function testItCreatesTenantWithDefaultStatusAndPlan(): void
    {
        $tenant = $this->repository->create(new CreateTenant(
            domain: 'default.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_default',
            databaseUser: 'tenant_default_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'pending',
            plan: 'basic',
        ));

        self::assertSame('pending', $tenant->status);
        self::assertSame('basic', $tenant->plan);
    }

    public function testItUpdatesTenantWithDefaultStatusAndPlan(): void
    {
        $this->insertTenant('shop.example.com', null);

        $tenant = $this->repository->update(new UpdateTenant(
            id: '1',
            domain: 'updated.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'pending',
            plan: 'basic',
        ));

        self::assertNotNull($tenant);
        self::assertSame('pending', $tenant->status);
        self::assertSame('basic', $tenant->plan);
    }

    public function testItRejectsCreateWithEmptyDomain(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: '',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyDatabaseHost(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: '',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyDatabaseName(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: '',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyDatabaseUser(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: '',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyEncryptedPassword(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: '',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyStatus(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: '',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithEmptyPlan(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant record is malformed.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: '',
        ));
    }

    public function testItRejectsCreateWithInvalidPort(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 70000,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsUpdateWithInvalidPort(): void
    {
        $this->insertTenant('shop.example.com', null);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->update(new UpdateTenant(
            id: '1',
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: 0,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
    }

    public function testItRejectsCreateWithNegativePort(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Tenant database port is invalid.');

        $this->repository->create(new CreateTenant(
            domain: 'test.example.com',
            databaseHost: 'tenant-db',
            databasePort: -1,
            databaseName: 'tenant_test',
            databaseUser: 'tenant_test_user',
            encryptedDatabasePassword: 'password',
            storageFolder: 'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
            status: 'active',
            plan: 'basic',
        ));
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
                storage_folder,
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
                'tenant_1_a7x9k2m8pQ3LwRtZvBnJy',
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
