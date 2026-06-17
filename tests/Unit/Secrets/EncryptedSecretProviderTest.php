<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Secrets;

use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Secrets\EncryptedSecretProvider;
use PHPUnit\Framework\TestCase;

final class EncryptedSecretProviderTest extends TestCase
{
    private EncryptionService $encryption;
    private EncryptedSecretProvider $provider;

    protected function setUp(): void
    {
        $key = EncryptionService::generateKey();
        $this->encryption = new EncryptionService($key);
        $this->provider = new EncryptedSecretProvider($this->encryption);
    }

    public function testItDecryptsTenantPassword(): void
    {
        $plaintextPassword = 'my_secret_password_123';
        $encryptedPassword = $this->encryption->encrypt($plaintextPassword);

        $tenant = $this->createTenant($encryptedPassword);

        $result = $this->provider->getDatabasePassword($tenant);

        self::assertSame($plaintextPassword, $result);
    }

    public function testItThrowsExceptionForEmptyPassword(): void
    {
        $tenant = $this->createTenant('');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('empty');

        $this->provider->getDatabasePassword($tenant);
    }

    public function testItThrowsExceptionForInvalidEncryptedData(): void
    {
        $tenant = $this->createTenant('invalid_encrypted_data');

        $this->expectException(ConfigurationException::class);

        $this->provider->getDatabasePassword($tenant);
    }

    public function testItDecryptsComplexPasswords(): void
    {
        $complexPassword = 'P@ssw0rd!#$%^&*()_+-=[]{}|;:,.<>?';
        $encryptedPassword = $this->encryption->encrypt($complexPassword);

        $tenant = $this->createTenant($encryptedPassword);

        $result = $this->provider->getDatabasePassword($tenant);

        self::assertSame($complexPassword, $result);
    }

    public function testItDecryptsLongPasswords(): void
    {
        $longPassword = bin2hex(random_bytes(64)); // 128 character password
        $encryptedPassword = $this->encryption->encrypt($longPassword);

        $tenant = $this->createTenant($encryptedPassword);

        $result = $this->provider->getDatabasePassword($tenant);

        self::assertSame($longPassword, $result);
    }

    private function createTenant(string $encryptedPassword): Tenant
    {
        return new Tenant(
            id: '1',
            domain: 'shop.example.com',
            databaseHost: 'tenant-db',
            databasePort: 3306,
            databaseName: 'tenant_1',
            databaseUser: 'tenant_1_user',
            encryptedDatabasePassword: $encryptedPassword,
            status: 'pending',
            plan: 'business',
            metadata: [],
        );
    }
}
