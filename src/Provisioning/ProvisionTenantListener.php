<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Provisioning;

use MrKindy\MultiTenantWordPress\Contracts\JobDispatcherInterface;
use MrKindy\MultiTenantWordPress\Events\TenantCreated;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Event listener that dispatches provisioning jobs when tenants are created.
 *
 * This listener subscribes to TenantCreated events and automatically
 * queues a ProvisionTenantJob for asynchronous processing.
 */
final readonly class ProvisionTenantListener
{
    public function __construct(
        private JobDispatcherInterface $jobDispatcher,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Handle the TenantCreated event.
     *
     * Creates a ProvisionTenantJob and dispatches it to the queue.
     */
    public function handle(TenantCreated $event): void
    {
        $this->logger->info('TenantCreated event received, dispatching provisioning job', [
            'tenant_id' => $event->tenant->id,
            'domain' => $event->tenant->domain,
        ]);

        $job = new ProvisionTenantJob(
            tenantId: $event->tenant->id,
            adminUsername: $event->adminUsername,
            adminEmail: $event->adminEmail,
            adminPassword: $event->adminPassword,
        );

        $this->jobDispatcher->dispatch($job);

        $this->logger->debug('ProvisionTenantJob dispatched', [
            'tenant_id' => $event->tenant->id,
        ]);
    }
}
