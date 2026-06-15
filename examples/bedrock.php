<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Config\Config;

/*
 * Place this near the top of Bedrock's config/application.php, after Composer
 * autoloading and environment loading, but before Roots\Config::apply().
 */
Bootstrap::boot(new Config(
    controlDatabaseHost: env('CONTROL_DB_HOST'),
    controlDatabasePort: (int) env('CONTROL_DB_PORT', 3306),
    controlDatabaseName: env('CONTROL_DB_NAME'),
    controlDatabaseUser: env('CONTROL_DB_USER'),
    controlDatabasePassword: env('CONTROL_DB_PASSWORD'),
    secretProvider: env('TENANT_SECRET_PROVIDER', Config::SECRET_PROVIDER_ENV),
    trustedDomainSuffixes: ['*.example.com'],
    awsRegion: env('AWS_REGION', 'us-east-1'),
));

// Do not define DB_NAME, DB_USER, DB_PASSWORD, or DB_HOST elsewhere in Bedrock.
