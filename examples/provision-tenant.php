<?php

declare(strict_types=1);

use MrKindy\MultiTenantWordPress\DTO\CreateTenant;
use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\Encryption\EncryptionService;
use MrKindy\MultiTenantWordPress\Provisioning\AdminAccountSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\DatabaseManager;
use MrKindy\MultiTenantWordPress\Provisioning\DatabaseNameGenerator;
use MrKindy\MultiTenantWordPress\Provisioning\DefaultDataSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\TenantProvisioner;
use MrKindy\MultiTenantWordPress\Provisioning\WooCommerceSeeder;
use MrKindy\MultiTenantWordPress\Provisioning\WordPressBootstrapper;
use MrKindy\MultiTenantWordPress\Provisioning\WordPressInstaller;
use MrKindy\MultiTenantWordPress\Repository\PdoTenantRepository;
use MrKindy\MultiTenantWordPress\Secrets\EncryptedSecretProvider;

require_once __DIR__ . '/../vendor/autoload.php';

// Configuration
$wpPath = getenv('WPPATH') ?: '/var/www/bedrock/web/wp';
$encryptionKey = getenv('TENANT_ENCRYPTION_KEY') ?: '';

if ($encryptionKey === '') {
    exit("TENANT_ENCRYPTION_KEY environment variable is required.\n");
}

// Connect to control database with elevated privileges for provisioning
$controlPdo = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        getenv('CONTROL_DB_HOST') ?: 'control-db',
        (int) (getenv('CONTROL_DB_PORT') ?: 3306),
        getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    ),
    getenv('CONTROL_DB_PROVISIONING_USER') ?: 'wordpress_control_provisioning',
    getenv('CONTROL_DB_PROVISIONING_PASSWORD') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

// Services
$encryption = new EncryptionService($encryptionKey);
$repository = new PdoTenantRepository($controlPdo);
$databaseManager = new DatabaseManager($controlPdo);
$secretProvider = new EncryptedSecretProvider($encryption);
$bootstrapper = new WordPressBootstrapper($wpPath);
$wordpressInstaller = new WordPressInstaller($bootstrapper);
$dataSeeder = new DefaultDataSeeder($bootstrapper);
$adminSeeder = new AdminAccountSeeder($bootstrapper);
$wooCommerceSeeder = new WooCommerceSeeder($bootstrapper);

// Create tenant record first
$tenantPassword = bin2hex(random_bytes(32));
$encryptedPassword = $encryption->encrypt($tenantPassword);

$tenant = $repository->create(new CreateTenant(
    domain: 'shop.example.com',
    databaseHost: 'tenant-db.internal',
    databasePort: 3306,
    databaseName: 'tenant_shop_example',
    databaseUser: 'tenant_shop_user',
    encryptedDatabasePassword: $encryptedPassword,
    status: 'pending',
    plan: 'business',
    metadata: [],
));

echo "Created tenant record: {$tenant->id} for {$tenant->domain}\n";

// Provision the tenant
$provisioner = new TenantProvisioner(
    $repository,
    $secretProvider,
    $databaseManager,
    $wordpressInstaller,
    $dataSeeder,
    $adminSeeder,
    $wooCommerceSeeder,
);

$adminCredentials = new ProvisioningAdminCredentials(
    username: 'admin',
    email: 'admin@example.com',
    password: bin2hex(random_bytes(16)),
);

try {
    $result = $provisioner->provision($tenant, $adminCredentials);

    echo "\nProvisioning completed successfully!\n";
    echo "Tenant ID: {$result->tenant->id}\n";
    echo "Domain: {$result->tenant->domain}\n";
    echo "Status: {$result->tenant->status}\n";
    echo "Installed at: {$result->installedAt->format('Y-m-d H:i:s')}\n";
    echo "\nWordPress Admin Credentials:\n";
    echo "Username: {$result->adminCredentials->username}\n";
    echo "Email: {$result->adminCredentials->email}\n";
    echo "Password: {$result->adminCredentials->password}\n";

    if ($result->pageIds !== []) {
        echo "\nCreated Pages:\n";
        foreach ($result->pageIds as $slug => $id) {
            echo "  - {$slug}: {$id}\n";
        }
    }
} catch (\MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException $e) {
    echo "Provisioning failed: {$e->getMessage()}\n";
    echo "Step: {$e->step}\n";
    exit(1);
}
