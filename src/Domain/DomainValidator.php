<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Domain;

use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;

final readonly class DomainValidator
{
    public function __construct(private bool $allowLocalhost = false)
    {
    }

    public function normalizeAndValidate(string $host): string
    {
        $domain = strtolower(trim($host));

        if ($domain === '' || preg_match('/[\x00-\x20\x7f]/', $domain) === 1) {
            throw new InvalidDomainException();
        }

        $domain = $this->removePort($domain);
        $domain = rtrim($domain, '.');

        if ($domain === '') {
            throw new InvalidDomainException();
        }

        if (filter_var($domain, FILTER_VALIDATE_IP) !== false) {
            throw new InvalidDomainException();
        }

        if ($domain === 'localhost') {
            if ($this->allowLocalhost) {
                return $domain;
            }

            throw new InvalidDomainException();
        }

        if (
            strlen($domain) > 253
            || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            throw new InvalidDomainException();
        }

        return $domain;
    }

    private function removePort(string $host): string
    {
        if (!str_contains($host, ':')) {
            return $host;
        }

        if (substr_count($host, ':') !== 1) {
            throw new InvalidDomainException();
        }

        [$domain, $port] = explode(':', $host, 2);

        if (
            $domain === ''
            || $port === ''
            || !ctype_digit($port)
            || (int) $port < 1
            || (int) $port > 65535
        ) {
            throw new InvalidDomainException();
        }

        return $domain;
    }
}
