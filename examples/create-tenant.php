<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;

require_once __DIR__ . '/../vendor/autoload.php';

$pdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('CONTROL_DB_HOST') ?: 'control-db',
        (int) (getenv('CONTROL_DB_PORT') ?: 3306),
        getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    ),
    getenv('CONTROL_DB_WRITER_USER') ?: 'wordpress_control_writer',
    getenv('CONTROL_DB_WRITER_PASSWORD') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

$repository = new PdoTenantRepository($pdo);
$tenant = $repository->create(new CreateTenant(
    domain: 'shop.example.com',
    databaseHost: 'tenant-db-42.internal',
    databasePort: 3306,
    databaseName: 'tenant_42',
    databaseUser: 'tenant_42_user',
    encryptedDatabasePassword: 'TENANT_42_DATABASE_PASSWORD',
    status: 'active',
    plan: 'business',
    metadata: ['uploads_path' => '/srv/uploads/tenant-42'],
));

printf("Created tenant %s for %s\n", $tenant->id, $tenant->domain);
