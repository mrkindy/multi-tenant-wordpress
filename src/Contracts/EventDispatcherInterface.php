<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Contracts;

/**
 * Interface for dispatching events within the provisioning system.
 *
 * This abstraction allows integration with any event system
 * (Laravel Events, Symfony EventDispatcher, custom implementations).
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch an event to all registered listeners.
     *
     * @param object $event The event object to dispatch
     */
    public function dispatch(object $event): void;
}
