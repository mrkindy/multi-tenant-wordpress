<?php

/**
 * Manages tenant database infrastructure using PDO.
 *
 * Supports MySQL, MariaDB, and Amazon RDS.
 * Uses IF NOT EXISTS for idempotent operations.
 */

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\DatabaseManagerInterface;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;
use PDO;
use PDOException;

readonly class DatabaseManager implements DatabaseManagerInterface
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function createDatabase(string $name): void
    {
        $this->validateIdentifier($name);

        try {
            // Use backticks for safety, though identifier is validated
            $sql = "CREATE DATABASE IF NOT EXISTS `{$name}` " .
                   "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw new TenantProvisioningException(
                "Failed to create database: {$e->getMessage()}",
                'database_creation',
                $name,
                $e,
            );
        }
    }

    public function createDatabaseUser(string $username, string $password, string $database): void
    {
        $this->validateIdentifier($username);
        $this->validateIdentifier($database);

        try {
            // Check if user exists first (for idempotency)
            if ($this->userExists($username)) {
                // User exists, just ensure privileges are correct
                $this->grantPrivileges($username, $database);
                return;
            }

            // Create user with MySQL 8.0+ compatible syntax
            // Use prepared statement for password to prevent injection
            $stmt = $this->pdo->prepare("CREATE USER :username@'%' IDENTIFIED BY :password");
            $stmt->execute(['username' => $username, 'password' => $password]);

            // Grant privileges
            $this->grantPrivileges($username, $database);
        } catch (PDOException $e) {
            throw new TenantProvisioningException(
                "Failed to create database user: {$e->getMessage()}",
                'database_user_creation',
                $username,
                $e,
            );
        }
    }

    public function dropDatabase(string $name): void
    {
        $this->validateIdentifier($name);

        try {
            $this->pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
        } catch (PDOException $e) {
            throw new TenantProvisioningException(
                "Failed to drop database: {$e->getMessage()}",
                'database_deletion',
                $name,
                $e,
            );
        }
    }

    public function dropDatabaseUser(string $username): void
    {
        $this->validateIdentifier($username);

        try {
            $this->pdo->exec("DROP USER IF EXISTS `{$username}`@'%'");
        } catch (PDOException $e) {
            throw new TenantProvisioningException(
                "Failed to drop database user: {$e->getMessage()}",
                'database_user_deletion',
                $username,
                $e,
            );
        }
    }

    public function databaseExists(string $name): bool
    {
        $this->validateIdentifier($name);

        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.schemata WHERE schema_name = :name");
        $stmt->execute(['name' => $name]);

        $result = $stmt->fetchColumn();

        return $result !== false && $result !== null;
    }

    public function userExists(string $username): bool
    {
        $this->validateIdentifier($username);

        // MySQL 8.0+ uses mysql.user, older versions may differ
        $stmt = $this->pdo->prepare("SELECT 1 FROM mysql.user WHERE user = :username");
        $stmt->execute(['username' => $username]);

        $result = $stmt->fetchColumn();

        return $result !== false && $result !== null;
    }

    /**
     * Grant required privileges to a user on a database.
     */
    private function grantPrivileges(string $username, string $database): void
    {
        // Grant only necessary privileges on the specific database
        $privileges = 'SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP';
        $this->pdo->exec("GRANT {$privileges} ON `{$database}`.* TO `{$username}`@'%'");
        $this->pdo->exec('FLUSH PRIVILEGES');
    }

    /**
     * Validate that an identifier is safe for use in SQL.
     *
     * @throws TenantProvisioningException If identifier is invalid
     */
    private function validateIdentifier(string $identifier): void
    {
        // MySQL identifiers: alphanumeric, underscore, max 64 chars
        // Must not start with a number
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new TenantProvisioningException(
                "Invalid database identifier: {$identifier}",
                'validation',
                $identifier,
            );
        }

        if (strlen($identifier) > 64) {
            throw new TenantProvisioningException(
                "Database identifier too long: {$identifier}",
                'validation',
                $identifier,
            );
        }
    }
}
