<?php

/**
 * Seeds default WordPress content for a new tenant.
 *
 * Creates default pages and configures WordPress options.
 * This class is idempotent - safe to run multiple times.
 */

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\DTO\ProvisioningAdminCredentials;
use MrKindy\MultiTenantWordPress\DTO\Tenant;
use MrKindy\MultiTenantWordPress\Exceptions\TenantProvisioningException;

readonly class DefaultDataSeeder
{
    private const PROVISION_MARK_OPTION = 'tenant_provision_mark';

    public function __construct(
        private WordPressBootstrapper $bootstrapper,
    ) {
    }

    /**
     * Seed default WordPress data.
     *
     * @param Tenant $tenant The tenant being provisioned
     * @param string $password The decrypted database password
     * @param ProvisioningAdminCredentials $admin The admin credentials
     * @return array<string, int> Map of page slugs to post IDs
     * @throws TenantProvisioningException If seeding fails
     */
    public function seed(
        Tenant $tenant,
        string $password,
        ProvisioningAdminCredentials $admin,
    ): array {
        // Bootstrap WordPress
        $this->bootstrapper->bootstrap($tenant, $password);

        // Check if already seeded (idempotency)
        if ($this->isAlreadySeeded()) {
            return $this->getExistingPageIds();
        }

        try {
            // Remove WordPress default content
            $this->removeSampleContent();

            // Create default pages
            $pageIds = [];

            $pageIds['home'] = $this->createPage(
                'Home',
                'Welcome to your new site.',
                '',
                'publish',
            );

            $pageIds['privacy-policy'] = $this->createPage(
                'Privacy Policy',
                $this->getPrivacyPolicyContent(),
                'privacy-policy',
                'publish',
            );

            $pageIds['terms-conditions'] = $this->createPage(
                'Terms & Conditions',
                $this->getTermsContent(),
                'terms-conditions',
                'publish',
            );

            // Configure WordPress options
            $this->configureOptions($pageIds, $admin);

            // Mark as provisioned
            update_option(self::PROVISION_MARK_OPTION, '1');

            return $pageIds;
        } catch (TenantProvisioningException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TenantProvisioningException(
                "Failed to seed default data: {$e->getMessage()}",
                'data_seeding',
                $tenant->id,
                $e,
            );
        }
    }

    /**
     * Check if the tenant has already been provisioned.
     */
    private function isAlreadySeeded(): bool
    {
        return get_option(self::PROVISION_MARK_OPTION) === '1';
    }

    /**
     * Get existing page IDs if already provisioned.
     *
     * @return array<string, int>
     */
    private function getExistingPageIds(): array
    {
        $pageIds = [];
        $slugs = ['home', 'privacy-policy', 'terms-conditions'];

        foreach ($slugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page instanceof \WP_Post) {
                $pageIds[$slug] = $page->ID;
            }
        }

        return $pageIds;
    }

    /**
     * Remove WordPress default sample content.
     */
    private function removeSampleContent(): void
    {
        // Remove default post "Hello World!"
        $defaultPost = get_page_by_path('hello-world', OBJECT, 'post');
        if ($defaultPost instanceof \WP_Post) {
            wp_delete_post($defaultPost->ID, true);
        }

        // Remove default page "Sample Page"
        $defaultPage = get_page_by_path('sample-page', OBJECT, 'page');
        if ($defaultPage instanceof \WP_Post) {
            wp_delete_post($defaultPage->ID, true);
        }

        // Remove default comment
        $defaultComment = get_comments(['number' => 1]);
        if ($defaultComment !== [] && isset($defaultComment[0]->comment_ID)) {
            wp_delete_comment($defaultComment[0]->comment_ID, true);
        }
    }

    /**
     * Create a WordPress page.
     *
     * @param string $title The page title
     * @param string $content The page content
     * @param string $slug The page slug
     * @param string $status The post status
     * @return int The post ID
     */
    private function createPage(
        string $title,
        string $content,
        string $slug,
        string $status,
    ): int {
        $pageData = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $status,
            'post_type'    => 'page',
            'post_author'  => 1,
        ];

        if ($slug !== '') {
            $pageData['post_name'] = $slug;
        }

        $pageId = wp_insert_post($pageData, true);

        if ($pageId instanceof \WP_Error) {
            throw new TenantProvisioningException(
                "Failed to create page '{$title}': " . $pageId->get_error_message(),
                'page_creation',
                '',
            );
        }

        return (int) $pageId;
    }

    /**
     * Configure WordPress options.
     *
     * @param array<string, int> $pageIds Map of page slugs to IDs
     * @param ProvisioningAdminCredentials $admin The admin credentials
     */
    private function configureOptions(array $pageIds, ProvisioningAdminCredentials $admin): void
    {
        // Set front page to display a static page
        update_option('show_on_front', 'page');
        update_option('page_on_front', $pageIds['home'] ?? 0);

        // Set privacy policy page
        if (isset($pageIds['privacy-policy'])) {
            update_option('wp_page_for_privacy_policy', $pageIds['privacy-policy']);
        }

        // Update site title and tagline
        update_option('blogname', 'My WordPress Site');
        update_option('blogdescription', 'Just another WordPress site');

        // Set admin email
        update_option('admin_email', $admin->email);

        // Configure permalink structure
        update_option('permalink_structure', '/%postname%/');

        // Set timezone to UTC by default
        update_option('timezone_string', '');
        update_option('gmt_offset', '0');

        // Disable comments on pages by default
        update_option('default_comment_status', 'closed');
        update_option('default_ping_status', 'closed');
    }

    /**
     * Get default privacy policy content.
     */
    private function getPrivacyPolicyContent(): string
    {
        return <<<'HTML'
<h2>Who we are</h2>
<p>Our website address is: <strong>https://example.com</strong>.</p>

<h2>What personal data we collect and why we collect it</h2>
<p>We collect information you provide directly to us when using our services.</p>

<h2>Contact information</h2>
<p>If you have any questions about this Privacy Policy, please contact us.</p>
HTML;
    }

    /**
     * Get default terms and conditions content.
     */
    private function getTermsContent(): string
    {
        return <<<'HTML'
<h2>Terms of Service</h2>
<p>By accessing this website, you agree to be bound by these terms of service.</p>

<h2>Use License</h2>
<p>Permission is granted to temporarily access the materials on our website for personal, non-commercial use.</p>

<h2>Disclaimer</h2>
<p>The materials on our website are provided on an 'as is' basis.</p>
HTML;
    }
}
