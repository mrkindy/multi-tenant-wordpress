<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

/**
 * Interface for managing tenant database infrastructure.
 *
 * Implementations must support MySQL, MariaDB, and Amazon RDS.
 * All operations should be idempotent where possible.
 */
interface DatabaseManagerInterface
{
    /**
     * Create a new database if it does not exist.
     *
     * @throws \RuntimeException If database creation fails
     */
    public function createDatabase(string $name): void;

    /**
     * Create a new database user with limited privileges.
     *
     * Grants only required privileges on the specified database.
     *
     * @throws \RuntimeException If user creation fails
     */
    public function createDatabaseUser(string $username, string $password, string $database): void;

    /**
     * Drop a database if it exists.
     *
     * @throws \RuntimeException If database drop fails
     */
    public function dropDatabase(string $name): void;

    /**
     * Drop a database user if it exists.
     *
     * @throws \RuntimeException If user drop fails
     */
    public function dropDatabaseUser(string $username): void;

    /**
     * Check if a database exists.
     */
    public function databaseExists(string $name): bool;

    /**
     * Check if a database user exists.
     */
    public function userExists(string $username): bool;
}
