<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\WordPress;

use MrKindy\MultiTenantWordPress\Tests\Support\CreatesTenant;
use MrKindy\MultiTenantWordPress\WordPress\DatabaseConfigurator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class DatabaseConfiguratorTest extends TestCase
{
    use CreatesTenant;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItDefinesWordPressDatabaseConstants(): void
    {
        (new DatabaseConfigurator())->configure(
            $this->createTenant(),
            'database-secret',
        );

        self::assertSame('tenant_1', constant('DB_NAME'));
        self::assertSame('tenant_1_user', constant('DB_USER'));
        self::assertSame('database-secret', constant('DB_PASSWORD'));
        self::assertSame('tenant-db:3306', constant('DB_HOST'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testItDoesNotOverrideExistingConstants(): void
    {
        define('DB_NAME', 'existing_database');

        (new DatabaseConfigurator())->configure(
            $this->createTenant(),
            'database-secret',
        );

        self::assertSame('existing_database', constant('DB_NAME'));
    }
}
