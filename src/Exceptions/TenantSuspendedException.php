<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Exceptions;

use RuntimeException;

final class TenantSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This tenant is not active.');
    }
}
