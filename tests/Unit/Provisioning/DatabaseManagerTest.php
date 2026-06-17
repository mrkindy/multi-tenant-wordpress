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
}
