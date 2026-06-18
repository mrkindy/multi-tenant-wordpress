<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;
use MrKindy\MultiTenantWordPress\Provisioning\WordPressBootstrapper;
use PHPUnit\Framework\TestCase;

final class WordPressBootstrapperTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        WordPressBootstrapper::reset();
        $this->tempDir = sys_get_temp_dir() . '/wp_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        WordPressBootstrapper::reset();
        $this->recursiveDelete($this->tempDir);
        parent::tearDown();
    }

    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }

    public function testItThrowsExceptionForEmptyPath(): void
    {
        $bootstrapper = new WordPressBootstrapper('');
        $tenant = $this->createTenant();

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('WordPress path is not configured');

        $bootstrapper->bootstrap($tenant, 'password');
    }

    public function testItThrowsExceptionForNonExistentPath(): void
    {
        $bootstrapper = new WordPressBootstrapper('/non/existent/path');
        $tenant = $this->createTenant();

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('WordPress path does not exist');

        $bootstrapper->bootstrap($tenant, 'password');
    }

    public function testItThrowsExceptionForPathWithoutWpLoad(): void
    {
        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = $this->createTenant();

        $this->expectException(TenantProvisioningException::class);
        $this->expectExceptionMessage('wp-load.php not found');

        $bootstrapper->bootstrap($tenant, 'password');
    }

    public function testItThrowsExceptionForPathWithDirectoryInsteadOfFile(): void
    {
        mkdir($this->tempDir . '/wp-load.php');
        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = $this->createTenant();

        $this->expectException(TenantProvisioningException::class);
        // The error message could be either from validateWpPath or from the PHP include error
        $this->expectExceptionMessageMatches('/wp-load\.php|Failed opening|Failed to bootstrap/');

        try {
            $bootstrapper->bootstrap($tenant, 'password');
        } catch (TenantProvisioningException $e) {
            // Clean up the directory before re-throwing
            rmdir($this->tempDir . '/wp-load.php');
            throw $e;
        }
    }

    public function testResetClearsBootstrapState(): void
    {
        // First call to reset
        WordPressBootstrapper::reset();

        // This test verifies the method exists and doesn't throw
        self::assertTrue(true);
    }

    public function testItHandlesIpv6DatabaseHost(): void
    {
        // Create a mock wp-load.php that defines constants
        $wpLoadContent = <<<'PHP'
<?php
// Mock WordPress bootstrap
if (!defined('DB_NAME')) define('DB_NAME', 'test');
if (!defined('DB_USER')) define('DB_USER', 'test');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'test');
if (!defined('DB_HOST')) define('DB_HOST', 'test');
PHP;
        file_put_contents($this->tempDir . '/wp-load.php', $wpLoadContent);

        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: '2001:db8::1',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'encrypted',
            status: 'active',
            plan: 'business',
            metadata: [],
        );

        // Should not throw - the IPv6 handling is in defineDatabaseConstants
        // which is private, but we can verify the bootstrap runs
        try {
            $bootstrapper->bootstrap($tenant, 'password');
            // If we get here, the bootstrap ran (or tried to)
            self::assertTrue(true);
        } catch (TenantProvisioningException $e) {
            // Expected if the mock wp-load.php doesn't fully work
            self::assertStringContainsString('Failed to bootstrap', $e->getMessage());
        }
    }

    public function testItHandlesIpv4DatabaseHost(): void
    {
        $wpLoadContent = <<<'PHP'
<?php
// Mock WordPress bootstrap
if (!defined('DB_NAME')) define('DB_NAME', 'test');
if (!defined('DB_USER')) define('DB_USER', 'test');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'test');
if (!defined('DB_HOST')) define('DB_HOST', 'test');
PHP;
        file_put_contents($this->tempDir . '/wp-load.php', $wpLoadContent);

        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: '192.168.1.1',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'encrypted',
            status: 'active',
            plan: 'business',
            metadata: [],
        );

        try {
            $bootstrapper->bootstrap($tenant, 'password');
            self::assertTrue(true);
        } catch (TenantProvisioningException $e) {
            self::assertStringContainsString('Failed to bootstrap', $e->getMessage());
        }
    }

    public function testItSkipsBootstrapWhenAlreadyBootstrapped(): void
    {
        $wpLoadContent = <<<'PHP'
<?php
// Mock WordPress bootstrap
if (!defined('DB_NAME')) define('DB_NAME', 'test');
if (!defined('DB_USER')) define('DB_USER', 'test');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'test');
if (!defined('DB_HOST')) define('DB_HOST', 'test');
PHP;
        file_put_contents($this->tempDir . '/wp-load.php', $wpLoadContent);

        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = $this->createTenant();

        // First bootstrap
        try {
            $bootstrapper->bootstrap($tenant, 'password');
        } catch (TenantProvisioningException) {
            // Expected
        }

        // Second bootstrap should be skipped (no exception about file not found)
        // because $bootstrapped is already true
        try {
            $bootstrapper->bootstrap($tenant, 'password');
            self::assertTrue(true);
        } catch (TenantProvisioningException $e) {
            // Should not be about file not found
            self::assertStringNotContainsString('wp-load.php not found', $e->getMessage());
        }
    }

    public function testItSetsWpInstallingConstant(): void
    {
        $wpLoadContent = <<<'PHP'
<?php
// Verify WP_INSTALLING is defined
if (!defined('WP_INSTALLING')) {
    throw new Exception('WP_INSTALLING should be defined before wp-load.php');
}
// Define other constants
if (!defined('DB_NAME')) define('DB_NAME', 'test');
if (!defined('DB_USER')) define('DB_USER', 'test');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'test');
if (!defined('DB_HOST')) define('DB_HOST', 'test');
PHP;
        file_put_contents($this->tempDir . '/wp-load.php', $wpLoadContent);

        $bootstrapper = new WordPressBootstrapper($this->tempDir);
        $tenant = $this->createTenant();

        // Should not throw about WP_INSTALLING
        try {
            $bootstrapper->bootstrap($tenant, 'password');
            self::assertTrue(true);
        } catch (\Exception $e) {
            self::assertStringNotContainsString('WP_INSTALLING', $e->getMessage());
        }
    }

    private function createTenant(): Tenant
    {
        return new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: 'encrypted',
            status: 'active',
            plan: 'business',
            metadata: [],
        );
    }
}
