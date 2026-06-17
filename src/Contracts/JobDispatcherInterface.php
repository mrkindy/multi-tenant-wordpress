<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

/**
 * Interface for dispatching jobs to a queue/worker system.
 *
 * This abstraction allows integration with any queue system
 * (Laravel Queue, Symfony Messenger, custom background workers, etc.).
 */
interface JobDispatcherInterface
{
    /**
     * Dispatch a job for asynchronous processing.
     *
     * @param object $job The job object to dispatch
     */
    public function dispatch(object $job): void;
}
