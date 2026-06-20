<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Config;

use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\Contracts\DatabaseNameGeneratorInterface;
use MrKindy\MultiTenantWordPress\Contracts\EventDispatcherInterface;
use MrKindy\MultiTenantWordPress\Contracts\JobDispatcherInterface;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use Psr\Log\LoggerInterface;

final readonly class Config
{
    public const SECRET_PROVIDER_ENV = 'env';
    public const SECRET_PROVIDER_AWS = 'aws';
    public const SECRET_PROVIDER_ENCRYPTED = 'encrypted';
    public const CACHE_PROVIDER_ARRAY = 'array';

    /**
     * @param list<string> $trustedDomainSuffixes
     */
    public function __construct(
        public string $controlDatabaseHost,
        public int $controlDatabasePort,
        public string $controlDatabaseName,
        public string $controlDatabaseUser,
        public string $controlDatabasePassword,
        public string $secretProvider = self::SECRET_PROVIDER_ENCRYPTED,
        public string $encryptionKey = '',
        public string $cacheProvider = self::CACHE_PROVIDER_ARRAY,
        public array $trustedDomainSuffixes = [],
        public bool $allowLocalhost = false,
        public int $cacheTtlSeconds = 60,
        public string $awsRegion = 'us-east-1',
        public string $awsSecretPasswordKey = 'password',
        public ?LoggerInterface $logger = null,
        public bool $enableDebugging = false,
        public ?TenantRepositoryInterface $tenantRepository = null,
        public ?SecretProviderInterface $customSecretProvider = null,
        public ?CacheInterface $customCache = null,
        // Provisioning configuration
        public string $wpPath = '',
        public string $databaseNamePrefix = 'tenant_',
        public string $databaseUserPrefix = 'tenant_',
        public ?string $controlDatabaseProvisioningUser = null,
        public ?string $controlDatabaseProvisioningPassword = null,
        public ?EventDispatcherInterface $eventDispatcher = null,
        public ?JobDispatcherInterface $jobDispatcher = null,
        public ?DatabaseNameGeneratorInterface $databaseNameGenerator = null,
    ) {
        if (
            $this->controlDatabaseHost === ''
            || $this->controlDatabaseName === ''
            || $this->controlDatabaseUser === ''
        ) {
            throw new ConfigurationException('Control database configuration is incomplete.');
        }

        if (
            preg_match('/[\x00-\x20;=]/', $this->controlDatabaseHost) === 1
            || preg_match('/^[A-Za-z0-9_$-]+$/', $this->controlDatabaseName) !== 1
        ) {
            throw new ConfigurationException('Control database identifier is invalid.');
        }

        if ($this->controlDatabasePort < 1 || $this->controlDatabasePort > 65535) {
            throw new ConfigurationException('Control database port is invalid.');
        }

        if ($this->cacheTtlSeconds < 0) {
            throw new ConfigurationException('Cache TTL cannot be negative.');
        }

        if ($this->customCache === null) {
            $this->validateEncryptionKey($this->encryptionKey);
        }

        if (
            $this->customSecretProvider === null
            && !in_array(
                $this->secretProvider,
                [self::SECRET_PROVIDER_ENV, self::SECRET_PROVIDER_AWS, self::SECRET_PROVIDER_ENCRYPTED],
                true,
            )
        ) {
            throw new ConfigurationException('Secret provider is not supported.');
        }

        if (
            $this->customCache === null
            && $this->cacheProvider !== self::CACHE_PROVIDER_ARRAY
        ) {
            throw new ConfigurationException('Cache provider is not supported.');
        }
    }

    private function validateEncryptionKey(string $base64Key): void
    {
        $key = base64_decode($base64Key, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new ConfigurationException('Encryption key is invalid.');
        }

        sodium_memzero($key);
    }
}
