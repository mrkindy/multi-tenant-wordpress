<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when tenant provisioning fails.
 */
final class TenantProvisioningException extends RuntimeException
{
    /**
     * @param string $message The error message
     * @param string $step The provisioning step that failed
     * @param string $tenantId The tenant ID being provisioned
     * @param Throwable|null $previous The previous exception
     */
    public function __construct(
        string $message,
        public readonly string $step,
        public readonly string $tenantId,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
