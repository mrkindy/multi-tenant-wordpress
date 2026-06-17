<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;

/**
 * Creates the WordPress administrator account for a tenant.
 *
 * This class is idempotent - safe to run multiple times.
 */
readonly class AdminAccountSeeder
{
    public function __construct(
        private WordPressBootstrapper $bootstrapper,
    ) {
    }

    /**
     * Create or update the WordPress administrator account.
     *
     * @param Tenant $tenant The tenant being provisioned
     * @param string $password The decrypted database password
     * @param ProvisioningAdminCredentials $admin The admin credentials
     * @return int The WordPress user ID
     * @throws TenantProvisioningException If admin creation fails
     */
    public function createAdmin(
        Tenant $tenant,
        string $password,
        ProvisioningAdminCredentials $admin,
    ): int {
        // Bootstrap WordPress
        $this->bootstrapper->bootstrap($tenant, $password);

        try {
            // Check if user already exists
            $existingUser = username_exists($admin->username);

            if ($existingUser) {
                // User exists, ensure they have admin role
                $user = new \WP_User($existingUser);
                $user->set_role('administrator');

                return (int) $existingUser;
            }

            // Create new admin user
            $userId = wp_insert_user([
                'user_login' => $admin->username,
                'user_email' => $admin->email,
                'user_pass'  => $admin->password,
                'user_nicename' => sanitize_title($admin->username),
                'display_name' => $admin->username,
                'role'       => 'administrator',
            ]);

            if ($userId instanceof \WP_Error) {
                throw new TenantProvisioningException(
                    "Failed to create admin user: " . $userId->get_error_message(),
                    'admin_creation',
                    $tenant->id,
                );
            }

            return (int) $userId;
        } catch (TenantProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TenantProvisioningException(
                "Failed to create admin account: {$e->getMessage()}",
                'admin_creation',
                $tenant->id,
                $e,
            );
        }
    }
}
