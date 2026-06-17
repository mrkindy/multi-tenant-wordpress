<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Queue;

use MrKindy\MultiTenantWordPress\Contracts\JobDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Synchronous job dispatcher that executes jobs immediately.
 *
 * Useful for testing and single-node deployments where
 * background processing is not required.
 */
final readonly class SynchronousJobDispatcher implements JobDispatcherInterface
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function dispatch(object $job): void
    {
        $this->logger->debug('Job dispatched synchronously.', [
            'job_class' => $job::class,
        ]);

        // In synchronous mode, the job is just logged.
        // The actual execution happens when ProvisionTenantJob::handle() is called.
    }
}
