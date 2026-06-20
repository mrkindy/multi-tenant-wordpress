<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Provisioning\DatabaseNameGenerator;
use MrKindy\MultiTenantWordPress\Provisioning\StorageFolderGenerator;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;

require_once __DIR__ . '/../vendor/autoload.php';

$encryptionKey = getenv('TENANT_ENCRYPTION_KEY') ?: '';
if ($encryptionKey === '') {
    exit("TENANT_ENCRYPTION_KEY environment variable is required.\n");
}

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
$encryption = new EncryptionService($encryptionKey);
$nameGenerator = new DatabaseNameGenerator();
$storageFolderGenerator = new StorageFolderGenerator();

// Tenant details
$domain = 'shop.example.com';
$tenantId = uniqid('t_', true);

// Generate database credentials
$databaseName = $nameGenerator->generateDatabaseName($tenantId, $domain);
$databaseUser = $nameGenerator->generateDatabaseUser($tenantId, $domain);
$databasePassword = bin2hex(random_bytes(32));

// Encrypt the password for storage
$encryptedPassword = $encryption->encrypt($databasePassword);

// Generate storage folder (will be updated with actual tenant ID after creation)
$storageFolder = $storageFolderGenerator->generate('temp');

// Create tenant with 'pending' status - provisioning will happen separately
$tenant = $repository->create(new CreateTenant(
    domain: $domain,
    databaseHost: 'tenant-db.internal',
    databasePort: 3306,
    databaseName: $databaseName,
    databaseUser: $databaseUser,
    encryptedDatabasePassword: $encryptedPassword,
    storageFolder: $storageFolder,
    status: 'pending',
    plan: 'business',
    metadata: [],
));

echo "Created tenant {$tenant->id} for {$tenant->domain}\n";
echo "Database: {$tenant->databaseName}\n";
echo "Database User: {$tenant->databaseUser}\n";
echo "Storage Folder: {$tenant->storageFolder}\n";
echo "Status: {$tenant->status}\n";
echo "\nNext step: Run provision-tenant.php to install WordPress.\n";
