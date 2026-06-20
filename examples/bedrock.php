<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Config\Config;

/*
 * Place this near the top of Bedrock's config/application.php, after Composer
 * autoloading and environment loading, but before Roots\Config::apply().
 */
Bootstrap::boot(new Config(
    controlDatabaseHost: getenv('DB_HOST') ?: 'localhost',
    controlDatabasePort: (int) (getenv('DB_PORT') ?: 3306),
    controlDatabaseName: getenv('DB_NAME'),
    controlDatabaseUser: getenv('DB_USER'),
    controlDatabasePassword: getenv('DB_PASSWORD') ?: '',
    encryptionKey: getenv('TENANT_ENCRYPTION_KEY') ?: '',
    enableDebugging: true,
    cacheProvider: Config::CACHE_PROVIDER_ARRAY,
    trustedDomainSuffixes: ['*.example.com'],
    allowLocalhost: false,
    cacheTtlSeconds: 60,
));
// Do not define DB_NAME, DB_USER, DB_PASSWORD, or DB_HOST elsewhere in Bedrock.
