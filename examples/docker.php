<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Config\Config;

// Mount this file and require it from wp-config.php before wp-settings.php.
Bootstrap::boot(new Config(
    controlDatabaseHost: getenv('CONTROL_DB_HOST') ?: 'control-db',
    controlDatabasePort: (int) (getenv('CONTROL_DB_PORT') ?: 3306),
    controlDatabaseName: getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    controlDatabaseUser: getenv('CONTROL_DB_USER') ?: 'wordpress_control',
    controlDatabasePassword: getenv('CONTROL_DB_PASSWORD') ?: '',
    secretProvider: getenv('TENANT_SECRET_PROVIDER') ?: Config::SECRET_PROVIDER_ENV,
    trustedDomainSuffixes: array_values(array_filter(
        explode(',', getenv('TRUSTED_DOMAIN_SUFFIXES') ?: '*.example.com'),
    )),
    allowLocalhost: filter_var(
        getenv('ALLOW_LOCALHOST') ?: 'false',
        FILTER_VALIDATE_BOOL,
    ),
    awsRegion: getenv('AWS_REGION') ?: 'us-east-1',
));
