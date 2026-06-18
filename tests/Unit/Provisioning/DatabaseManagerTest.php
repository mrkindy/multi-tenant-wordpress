<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;
use MrKindy\MultiTenantWordPress\Provisioning\DatabaseManager;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

final class DatabaseManagerTest extends TestCase
{
    private PDO $pdo;
    private DatabaseManager $manager;

    protected function setUp(): void
    {
        $this->pdo = $this->createMock(PDO::class);
        $this->manager = new DatabaseManager($this->pdo);
    }

    public function testItCreatesDatabaseWithValidName(): void
    {
        $this->pdo->expects(self::once())
            ->method('exec')
            ->with(self::stringContains('CREATE DATABASE IF NOT EXISTS `tenant_test`'));

        $this->manager->createDatabase('tenant_test');
    }

    public function testItThrowsExceptionForInvalidDatabaseName(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabase('invalid-name');
    }

    public function testItThrowsExceptionForDatabaseNameStartingWithNumber(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabase('123tenant');
    }

    public function testItChecksDatabaseExists(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('1');

        $this->pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('information_schema.schemata'))
            ->willReturn($statement);

        $exists = $this->manager->databaseExists('tenant_test');

        self::assertTrue($exists);
    }

    public function testItChecksDatabaseDoesNotExist(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn(false);

        $this->pdo->expects(self::once())
            ->method('prepare')
            ->willReturn($statement);

        $exists = $this->manager->databaseExists('tenant_nonexistent');

        self::assertFalse($exists);
    }

    public function testItChecksUserExists(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('1');

        $this->pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('mysql.user'))
            ->willReturn($statement);

        $exists = $this->manager->userExists('tenant_user');

        self::assertTrue($exists);
    }

    public function testItDropsDatabase(): void
    {
        $this->pdo->expects(self::once())
            ->method('exec')
            ->with(self::stringContains('DROP DATABASE IF EXISTS `tenant_test`'));

        $this->manager->dropDatabase('tenant_test');
    }

    public function testItDropsDatabaseUser(): void
    {
        $this->pdo->expects(self::once())
            ->method('exec')
            ->with(self::stringContains("DROP USER IF EXISTS `tenant_user`@'%'"));

        $this->manager->dropDatabaseUser('tenant_user');
    }

    public function testItValidatesIdentifierLength(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('too long');

        $longName = str_repeat('a', 65);
        $this->manager->createDatabase($longName);
    }

    public function testItCreatesDatabaseUserWithPrivileges(): void
    {
        $callCount = 0;
        $this->pdo->method('prepare')
            ->willReturnCallback(function ($sql) use (&$callCount) {
                $statement = $this->createMock(PDOStatement::class);

                // First call: check if user exists
                if (str_contains($sql, 'mysql.user')) {
                    $statement->method('fetchColumn')->willReturn(false);
                }

                $callCount++;
                return $statement;
            });

        $this->pdo->method('exec')
            ->willReturnCallback(function ($sql) {
                // Verify GRANT and FLUSH PRIVILEGES are called
                self::assertTrue(
                    str_contains($sql, 'GRANT') || str_contains($sql, 'FLUSH PRIVILEGES')
                );
                return 1;
            });

        $this->manager->createDatabaseUser('tenant_user', 'password123', 'tenant_db');
    }

    public function testItSkipsUserCreationWhenUserExists(): void
    {
        $this->pdo->expects(self::once())
            ->method('prepare')
            ->with(self::stringContains('mysql.user'))
            ->willReturnCallback(function () {
                $statement = $this->createMock(PDOStatement::class);
                $statement->method('fetchColumn')->willReturn('1'); // User exists
                return $statement;
            });

        // Should only call GRANT and FLUSH, not CREATE USER
        $this->pdo->expects(self::exactly(2))
            ->method('exec')
            ->willReturnCallback(function ($sql) {
                self::assertTrue(
                    str_contains($sql, 'GRANT') || str_contains($sql, 'FLUSH PRIVILEGES')
                );
                return 1;
            });

        $this->manager->createDatabaseUser('existing_user', 'password123', 'tenant_db');
    }

    public function testItThrowsExceptionForInvalidUsername(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('invalid-user', 'password', 'tenant_db');
    }

    public function testItThrowsExceptionForInvalidDatabaseInCreateUser(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('valid_user', 'password', 'invalid-db');
    }

    public function testItThrowsExceptionWhenCreateUserFails(): void
    {
        $this->pdo->method('prepare')
            ->willReturnCallback(function () {
                $statement = $this->createMock(PDOStatement::class);
                $statement->method('fetchColumn')->willReturn(false); // User doesn't exist
                return $statement;
            });

        $this->pdo->method('exec')
            ->willThrowException(new \PDOException('Access denied'));

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Failed to create database user');

        $this->manager->createDatabaseUser('tenant_user', 'password', 'tenant_db');
    }

    public function testItThrowsExceptionWhenDropDatabaseFails(): void
    {
        $this->pdo->method('exec')
            ->willThrowException(new \PDOException('Permission denied'));

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Failed to drop database');

        $this->manager->dropDatabase('tenant_db');
    }

    public function testItThrowsExceptionWhenDropUserFails(): void
    {
        $this->pdo->method('exec')
            ->willThrowException(new \PDOException('Permission denied'));

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Failed to drop database user');

        $this->manager->dropDatabaseUser('tenant_user');
    }

    public function testItThrowsExceptionForInvalidDatabaseNameInDrop(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->dropDatabase('invalid-name');
    }

    public function testItThrowsExceptionForInvalidUsernameInDrop(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->dropDatabaseUser('invalid-user');
    }

    public function testItThrowsExceptionForInvalidDatabaseNameInExists(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->databaseExists('invalid;name');
    }

    public function testItThrowsExceptionForInvalidUsernameInExists(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->userExists('invalid;user');
    }

    public function testItThrowsExceptionForUsernameStartingWithNumber(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('123user', 'password', 'tenant_db');
    }

    public function testItThrowsExceptionForDatabaseNameStartingWithNumberInCreateUser(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('user', 'password', '123db');
    }

    public function testItThrowsExceptionForUsernameWithSpecialChars(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('user@domain', 'password', 'tenant_db');
    }

    public function testItThrowsExceptionForDatabaseNameWithSpecialChars(): void
    {
        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('Invalid database identifier');

        $this->manager->createDatabaseUser('user', 'password', 'db$name');
    }

    public function testItAcceptsValidUnderscoreIdentifiers(): void
    {
        $this->pdo->expects(self::once())
            ->method('exec')
            ->with(self::stringContains('CREATE DATABASE IF NOT EXISTS `tenant_test_db`'));

        $this->manager->createDatabase('tenant_test_db');
    }

    public function testItAcceptsValidIdentifiersWithNumbers(): void
    {
        $this->pdo->expects(self::once())
            ->method('exec')
            ->with(self::stringContains('CREATE DATABASE IF NOT EXISTS `tenant_123`'));

        $this->manager->createDatabase('tenant_123');
    }
}
