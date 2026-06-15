<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Encryption;

use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class EncryptionServiceTest extends TestCase
{
    public function testItEncryptsAndDecryptsASecret(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        $ciphertext = $service->encrypt('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $ciphertext);
        self::assertSame(
            'correct horse battery staple',
            $service->decrypt($ciphertext),
        );
    }

    public function testItRejectsInvalidKey(): void
    {
        $this->expectException(ConfigurationException::class);

        new EncryptionService('not-a-valid-key');
    }

    public function testItRejectsTamperedCiphertext(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        $payload = base64_decode($service->encrypt('secret'), true);
        self::assertIsString($payload);
        $payload[30] = chr(ord($payload[30]) ^ 1);

        $this->expectException(ConfigurationException::class);

        $service->decrypt(base64_encode($payload));
    }

    public function testItRejectsMalformedCiphertext(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());

        $this->expectException(ConfigurationException::class);

        $service->decrypt('not-base64');
    }
}
