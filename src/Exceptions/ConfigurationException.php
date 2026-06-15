<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Exceptions;

use RuntimeException;
use Throwable;

final class ConfigurationException extends RuntimeException
{
    public function __construct(
        string $message = 'Tenant configuration could not be loaded.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
