<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Exceptions;

use RuntimeException;

final class TenantNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tenant is configured for this host.');
    }
}
