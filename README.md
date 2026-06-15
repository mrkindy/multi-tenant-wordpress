# MrKindy Multi-Tenant WordPress

Database-per-tenant bootstrap for WordPress and WooCommerce SaaS platforms.
One WordPress codebase resolves the request host, reads tenant routing from an
independent control database, retrieves the database password from a secret
provider, and defines WordPress database constants before `wpdb` is created.

The package does not require WordPress Multisite, Bedrock, Laravel, or another
framework. It supports PHP 8.3+, WordPress Core, Bedrock, Docker, FrankenPHP,
Nginx, and Apache.

## Installation

```bash
composer require mrkindy/multi-tenant-wordpress
```

Required PHP extensions are PDO, JSON, and Sodium. The control database and
tenant databases require separate credentials. The control database account
should have read-only access to the `tenants` table.

## Request Lifecycle

`Bootstrap::boot()` performs these operations before WordPress loads:

1. Reads and normalizes `$_SERVER['HTTP_HOST']`.
2. Rejects empty, malformed, IP, untrusted, and disallowed localhost hosts.
3. Resolves the tenant through the cache and PDO control repository.
4. Rejects any tenant whose status is not `active`.
5. retrieves the tenant password through the configured secret provider.
6. Defines `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_HOST`.

Host validation intentionally happens before the control-database query.

## Configuration

```php
use MrKindy\MultiTenantWordPress\Bootstrap\Bootstrap;
use MrKindy\MultiTenantWordPress\Config\Config;

Bootstrap::boot(new Config(
    controlDatabaseHost: getenv('CONTROL_DB_HOST') ?: 'control-db',
    controlDatabasePort: (int) (getenv('CONTROL_DB_PORT') ?: 3306),
    controlDatabaseName: getenv('CONTROL_DB_NAME') ?: 'wordpress_control',
    controlDatabaseUser: getenv('CONTROL_DB_USER') ?: 'wordpress_control',
    controlDatabasePassword: getenv('CONTROL_DB_PASSWORD') ?: '',
    secretProvider: Config::SECRET_PROVIDER_ENV,
    cacheProvider: Config::CACHE_PROVIDER_ARRAY,
    trustedDomainSuffixes: ['*.example.com', '*.mrkindy.com'],
    allowLocalhost: false,
    cacheTtlSeconds: 60,
));
```

`trustedDomainSuffixes` accepts wildcard suffixes and literal suffixes.
`*.example.com` matches subdomains but not `example.com`; `example.com` matches
the apex and its subdomains. An empty list allows any syntactically valid
hostname. Production deployments should always provide an allowlist.

Custom implementations can be injected with `tenantRepository`,
`customSecretProvider`, and `customCache`. The bundled array cache is
request-local under traditional PHP and process-local under long-running
servers. Use a bounded external Redis or Memcached implementation in a
multi-node deployment.

## Control Database Schema

Apply [config/control-database.sql](config/control-database.sql) to a database
that is separate from all WordPress tenant databases.

The `encrypted_database_password` column stores an opaque reference, never a
plaintext password:

- With `EnvSecretsProvider`, store an environment variable name such as
  `TENANT_42_DATABASE_PASSWORD`.
- With `AwsSecretsProvider`, store an AWS Secrets Manager secret name or ARN.

Normalize domains to lowercase without ports or trailing dots before insert.
Each tenant database user should have access only to its own database.

Example record:

```sql
INSERT INTO tenants (
    domain, database_host, database_port, database_name, database_user,
    encrypted_database_password, status, plan, metadata
) VALUES (
    'shop.example.com', 'tenant-db-42.internal', 3306, 'tenant_42',
    'tenant_42_user', 'TENANT_42_DATABASE_PASSWORD', 'active', 'business',
    '{"uploads_path":"/srv/uploads/tenant-42"}'
);
```

`metadata.uploads_path` is reserved for future uploads isolation support. This
release isolates databases and configuration; it does not rewrite WordPress
upload paths.

## WordPress Core Integration

Require Composer and boot the package in `wp-config.php` before this line:

```php
require_once ABSPATH . 'wp-settings.php';
```

Do not define the four database constants before bootstrapping. See
[examples/wordpress-core.php](examples/wordpress-core.php) for generic HTTP
error handling. The bootstrap returns the resolved immutable `Tenant` DTO when
application code needs tenant metadata.

## Bedrock Integration

Place the bootstrap near the top of `config/application.php`, after Composer
autoloading and environment loading, but before `Roots\Config::apply()`.
Remove Bedrock's normal `DB_NAME`, `DB_USER`, `DB_PASSWORD`, and `DB_HOST`
definitions. See [examples/bedrock.php](examples/bedrock.php).

Bedrock is supported as an integration target, not required as a dependency.

## Docker Integration

Pass only control-plane credentials and secret-provider configuration to the
WordPress container. Do not inject every tenant password into a shared image.

```yaml
services:
  wordpress:
    environment:
      CONTROL_DB_HOST: control-db
      CONTROL_DB_PORT: 3306
      CONTROL_DB_NAME: wordpress_control
      CONTROL_DB_USER: wordpress_control_reader
      CONTROL_DB_PASSWORD_FILE: /run/secrets/control_db_password
      TENANT_SECRET_PROVIDER: aws
      TRUSTED_DOMAIN_SUFFIXES: "*.example.com"
      AWS_REGION: us-east-1
```

Docker secrets exposed as files should be read by the application's
configuration layer and passed to `Config`. See [examples/docker.php](examples/docker.php).
The same early-bootstrap rule applies to FrankenPHP, Nginx/PHP-FPM, and Apache.

## AWS Secrets Manager

Set `secretProvider` to `Config::SECRET_PROVIDER_AWS`. AWS credentials are
resolved by the AWS SDK default credential chain, so IAM roles for EC2, ECS, or
EKS are preferred over static access keys.

```php
$config = new Config(
    // Control database settings...
    secretProvider: Config::SECRET_PROVIDER_AWS,
    awsRegion: 'eu-central-1',
    awsSecretPasswordKey: 'password',
);
```

The AWS secret may be a raw password or a JSON object:

```json
{"password":"tenant-database-password"}
```

Grant the runtime identity `secretsmanager:GetSecretValue` only for tenant
secret ARNs it needs. Secret values are held in memory only long enough to
configure WordPress.

## Local Encryption

`EncryptionService` provides authenticated Sodium Secretbox encryption for
control-plane tooling:

```php
$encryption = new EncryptionService();
$key = $encryption->generateKey();
$ciphertext = $encryption->encrypt('secret', $key);
$plaintext = $encryption->decrypt($ciphertext, $key);
```

Keys are base64 encoded and must be stored outside the control database.
Secret-provider references remain the recommended runtime model.

## Security Model

- Host headers are validated before database or secret access.
- IP addresses, malformed ports, control characters, URL syntax, and direct
  localhost access are rejected by default.
- Tenant lookup uses a prepared PDO statement and never uses WordPress `wpdb`.
- Tenant database users should be unique and least-privileged.
- Passwords are retrieved through `SecretProviderInterface`.
- Expected request failures expose stable generic messages.
- Unexpected exceptions are logged through PSR-3 and wrapped in a generic
  `ConfigurationException`.
- Database constants are defined only when absent; pre-existing constants are
  not overwritten.

Behind a reverse proxy, configure the proxy to replace the incoming `Host`
header and allow only expected virtual hosts. This package deliberately does
not trust `X-Forwarded-Host`.

## Logging and Error Handling

Pass any PSR-3 logger through `Config::$logger`. Without one, `NullLogger` is
used. At the web boundary, catch package exceptions and return generic pages:

```php
try {
    Bootstrap::boot($config);
} catch (InvalidDomainException | TenantNotFoundException) {
    http_response_code(404);
    exit('Site not found.');
} catch (TenantSuspendedException) {
    http_response_code(403);
    exit('Site unavailable.');
} catch (Throwable) {
    http_response_code(503);
    exit('Service temporarily unavailable.');
}
```

Do not render exception traces or control-database errors to clients.

## Testing and Quality

```bash
composer install
composer check
vendor/bin/phpunit --coverage-text
```

CI tests PHP 8.3 and 8.4, runs PHPStan level 9, enforces PSR-12, and fails below
90% statement coverage.

## Troubleshooting

**WordPress connects to the old database**

The package ran after database constants were defined or after
`wp-settings.php`. Move `Bootstrap::boot()` earlier and remove old constants.

**Every request returns an invalid-host error**

Check the proxy-preserved `Host` value and `trustedDomainSuffixes`. Wildcard
entries do not match the apex domain.

**Tenant not found**

Store the normalized lowercase domain without a port or trailing dot. Confirm
the control database user can select from `tenants`.

**Secret unavailable**

For environment secrets, the reference must be an uppercase variable name.
For AWS, verify region, secret ID/ARN, IAM permissions, and the configured JSON
password key.

**Long-running server serves the wrong tenant**

Do not define database constants once and then reuse the same PHP worker for
different hosts. WordPress database constants are process-global and cannot be
changed. FrankenPHP worker mode or other persistent runtimes must isolate one
tenant per worker/process or use non-worker request execution.
