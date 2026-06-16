<?php

declare(strict_types=1);

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

if (!$repository->delete('42')) {
    exit("Tenant was not found.\n");
}

echo "Deleted tenant 42\n";
