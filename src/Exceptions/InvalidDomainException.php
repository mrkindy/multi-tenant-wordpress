<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Exceptions;

use InvalidArgumentException;

final class InvalidDomainException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The request host is invalid.');
    }
}
