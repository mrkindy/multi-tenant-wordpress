<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Config\Config;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantNotFoundException;
use MrKindy\MultiTenantWordPress\Exceptions\TenantSuspendedException;

require_once __DIR__ . '/../vendor/autoload.php';

$config = new Config(
    controlDatabaseHost: getenv('CONTROL_DB_HOST') ?: 'control-db',
    controlDatabasePort: (int) (getenv('CONTROL_DB_PORT') ?: 3306),
    controlDatabaseName: getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    controlDatabaseUser: getenv('CONTROL_DB_USER') ?: 'wordpress_control',
    controlDatabasePassword: getenv('CONTROL_DB_PASSWORD') ?: '',
    encryptionKey: getenv('TENANT_ENCRYPTION_KEY') ?: '',
    secretProvider: getenv('TENANT_SECRET_PROVIDER') ?: Config::SECRET_PROVIDER_ENV,
    trustedDomainSuffixes: ['*.example.com'],
);

try {
    Bootstrap::boot($config);
} catch (InvalidDomainException | TenantNotFoundException $exception) {
    http_response_code(404);
    exit('Site not found.');
} catch (TenantSuspendedException $exception) {
    http_response_code(403);
    exit('Site unavailable.');
} catch (Throwable $exception) {
    http_response_code(503);
    exit('Service temporarily unavailable.');
}

// Continue with the normal wp-config.php content, then load WordPress.
require_once ABSPATH . 'wp-settings.php';
