<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\DTO;

use DateTimeImmutable;

/**
 * Result object returned after successful tenant provisioning.
 */
final readonly class TenantProvisioningResult
{
    /**
     * @param Tenant $tenant The provisioned tenant
     * @param ProvisioningAdminCredentials $adminCredentials The WordPress admin credentials
     * @param DateTimeImmutable $installedAt When the installation completed
     * @param array<string, int> $pageIds Map of page slugs to WordPress post IDs
     */
    public function __construct(
        public Tenant $tenant,
        public ProvisioningAdminCredentials $adminCredentials,
        public DateTimeImmutable $installedAt,
        public array $pageIds = [],
    ) {
    }
}
