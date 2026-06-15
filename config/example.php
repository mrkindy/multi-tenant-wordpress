<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\Config\Config;

return new Config(
    controlDatabaseHost: getenv('CONTROL_DB_HOST') ?: 'control-db',
    controlDatabasePort: (int) (getenv('CONTROL_DB_PORT') ?: 3306),
    controlDatabaseName: getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    controlDatabaseUser: getenv('CONTROL_DB_USER') ?: 'wordpress_control',
    controlDatabasePassword: getenv('CONTROL_DB_PASSWORD') ?: '',
    encryptionKey: getenv('TENANT_ENCRYPTION_KEY') ?: '',
    secretProvider: getenv('TENANT_SECRET_PROVIDER') ?: Config::SECRET_PROVIDER_ENV,
    trustedDomainSuffixes: ['*.example.com', '*.mrkindy.com'],
    allowLocalhost: filter_var(
        getenv('ALLOW_LOCALHOST') ?: 'false',
        FILTER_VALIDATE_BOOL,
    ),
    awsRegion: getenv('AWS_REGION') ?: 'us-east-1',
);
