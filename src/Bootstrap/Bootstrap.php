<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Bootstrap;

use Aws\SecretsManager\SecretsManagerClient;
use MrKindy\MultiTenantWordPress\Cache\ArrayCache;
use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\Contracts\CacheInterface;
use MrKindy\MultiTenantWordPress\Contracts\SecretProviderInterface;
use MrKindy\MultiTenantWordPress\Contracts\TenantRepositoryInterface;
use MrKindy\MultiTenantWordPress\Domain\DomainValidator;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantNotFoundException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantSuspendedException;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use MrKindy\MultiTenantWordPress\Resolver\TenantResolver;
use MrKindy\MultiTenantWordPress\Secrets\AwsSecretsManagerClient;
use MrKindy\MultiTenantWordPress\Secrets\AwsSecretsProvider;
use MrKindy\MultiTenantWordPress\Secrets\EncryptedSecretProvider;
use MrKindy\MultiTenantWordPress\Secrets\EnvSecretsProvider;
use MrKindy\MultiTenantWordPress\Security\RequestValidator;
use MrKindy\MultiTenantWordPress\WordPress\DatabaseConfigurator;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

final class Bootstrap
{
    public static function boot(Config $config): Tenant
    {
        $logger = $config->logger ?? new NullLogger();

        try {
            if ($config->enableDebugging) {
                $whoops = new Run();
                $whoops->pushHandler(new PrettyPageHandler());
                $whoops->register();
            }

            $requestValidator = new RequestValidator(
                new DomainValidator($config->allowLocalhost),
                $config->trustedDomainSuffixes,
            );
            $domain = $requestValidator->validate($_SERVER);
            $resolver = new TenantResolver(
                self::repository($config),
                self::cache($config),
                $config->cacheTtlSeconds,
            );
            $tenant = $resolver->resolve($domain);
            $password = self::secretProvider($config)->getDatabasePassword($tenant);

            (new DatabaseConfigurator())->configure($tenant, $password);

            return $tenant;
        } catch (
            InvalidDomainException
            | TenantNotFoundException
            | TenantSuspendedException $exception
        ) {
            self::logExpectedFailure($logger, $exception);

            throw $exception;
        } catch (Throwable $exception) {
            $logger->error('Tenant bootstrap failed.', [
                'exception' => $exception,
            ]);

            throw new ConfigurationException(previous: $exception);
        }
    }

    private static function repository(Config $config): TenantRepositoryInterface
    {
        if ($config->tenantRepository !== null) {
            return $config->tenantRepository;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config->controlDatabaseHost,
            $config->controlDatabasePort,
            $config->controlDatabaseName,
        );
        $pdo = new PDO(
            $dsn,
            $config->controlDatabaseUser,
            $config->controlDatabasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return new PdoTenantRepository($pdo);
    }

    private static function cache(Config $config): CacheInterface
    {
        return $config->customCache ?? new ArrayCache(
            new EncryptionService($config->encryptionKey),
        );
    }

    private static function secretProvider(Config $config): SecretProviderInterface
    {
        if ($config->customSecretProvider !== null) {
            return $config->customSecretProvider;
        }

        if ($config->secretProvider === Config::SECRET_PROVIDER_AWS) {
            $client = new SecretsManagerClient([
                'version' => 'latest',
                'region' => $config->awsRegion,
            ]);

            return new AwsSecretsProvider(
                new AwsSecretsManagerClient($client),
                $config->awsSecretPasswordKey,
            );
        }

        if ($config->secretProvider === Config::SECRET_PROVIDER_ENV) {
            return  new EnvSecretsProvider();
        }

        $encryption = new EncryptionService($config->encryptionKey);
        return new EncryptedSecretProvider($encryption);
    }

    private static function logExpectedFailure(
        LoggerInterface $logger,
        Throwable $exception,
    ): void {
        $logger->warning('Tenant request rejected.', [
            'reason' => $exception::class,
        ]);
    }
}
