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

    public function testItRejectsEmptyKey(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Encryption key is invalid.');

        new EncryptionService('');
    }

    public function testItRejectsInvalidBase64Key(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Encryption key is invalid.');

        new EncryptionService('!!!invalid-base64!!!');
    }

    public function testItRejectsKeyWithWrongLength(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Encryption key is invalid.');

        // Valid base64 but wrong length (not 32 bytes when decoded)
        new EncryptionService(base64_encode('short-key'));
    }

    public function testItRejectsCiphertextWithOnlyNonce(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        // Create a payload that's exactly the nonce length (no ciphertext)
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Encrypted value is invalid.');

        $service->decrypt(base64_encode($nonce));
    }

    public function testItRejectsCiphertextShorterThanNonce(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        // Payload shorter than nonce length
        $shortPayload = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES - 1);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Encrypted value is invalid.');

        $service->decrypt(base64_encode($shortPayload));
    }

    public function testItRejectsDecryptionFailure(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        // Create a valid-looking payload with nonce + some ciphertext, but wrong key
        $otherService = new EncryptionService(EncryptionService::generateKey());
        $ciphertext = $otherService->encrypt('secret');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Decryption failed.');

        $service->decrypt($ciphertext);
    }

    public function testGenerateKeyCreatesValidKey(): void
    {
        $key = EncryptionService::generateKey();

        // Should be valid base64
        $decoded = base64_decode($key, true);
        self::assertIsString($decoded);
        // Should be exactly 32 bytes (SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
        self::assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decoded));

        // Should be usable to create an EncryptionService
        $service = new EncryptionService($key);
        $encrypted = $service->encrypt('test');
        self::assertSame('test', $service->decrypt($encrypted));
    }

    public function testItEncryptsEmptyString(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        $ciphertext = $service->encrypt('');

        self::assertNotSame('', $ciphertext);
        self::assertSame('', $service->decrypt($ciphertext));
    }

    public function testItEncryptsLargePayload(): void
    {
        $service = new EncryptionService(EncryptionService::generateKey());
        $largePayload = str_repeat('Lorem ipsum dolor sit amet. ', 1000);
        $ciphertext = $service->encrypt($largePayload);

        self::assertNotSame($largePayload, $ciphertext);
        self::assertSame($largePayload, $service->decrypt($ciphertext));
    }
}
