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
        $service = new EncryptionService();
        $key = $service->generateKey();
        $ciphertext = $service->encrypt('correct horse battery staple', $key);

        self::assertNotSame('correct horse battery staple', $ciphertext);
        self::assertSame(
            'correct horse battery staple',
            $service->decrypt($ciphertext, $key),
        );
    }

    public function testItRejectsInvalidKey(): void
    {
        $this->expectException(ConfigurationException::class);

        (new EncryptionService())->encrypt('secret', 'not-a-valid-key');
    }

    public function testItRejectsTamperedCiphertext(): void
    {
        $service = new EncryptionService();
        $key = $service->generateKey();
        $payload = base64_decode($service->encrypt('secret', $key), true);
        self::assertIsString($payload);
        $payload[30] = chr(ord($payload[30]) ^ 1);

        $this->expectException(ConfigurationException::class);

        $service->decrypt(base64_encode($payload), $key);
    }

    public function testItRejectsMalformedCiphertext(): void
    {
        $service = new EncryptionService();

        $this->expectException(ConfigurationException::class);

        $service->decrypt('not-base64', $service->generateKey());
    }
}
