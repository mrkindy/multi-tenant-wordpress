<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Encryption;

use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use SodiumException;

final class EncryptionService
{
    public function generateKey(): string
    {
        return base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function encrypt(string $plaintext, string $base64Key): string
    {
        $key = $this->decodeKey($base64Key);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        } catch (SodiumException $exception) {
            throw new ConfigurationException('Encryption failed.', $exception);
        } finally {
            sodium_memzero($key);
        }

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encodedCiphertext, string $base64Key): string
    {
        $key = $this->decodeKey($base64Key);
        $payload = base64_decode($encodedCiphertext, true);

        if (
            $payload === false
            || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
        ) {
            sodium_memzero($key);

            throw new ConfigurationException('Encrypted value is invalid.');
        }

        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } catch (SodiumException $exception) {
            throw new ConfigurationException('Decryption failed.', $exception);
        } finally {
            sodium_memzero($key);
        }

        if ($plaintext === false) {
            throw new ConfigurationException('Decryption failed.');
        }

        return $plaintext;
    }

    private function decodeKey(string $base64Key): string
    {
        $key = base64_decode($base64Key, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new ConfigurationException('Encryption key is invalid.');
        }

        return $key;
    }
}
