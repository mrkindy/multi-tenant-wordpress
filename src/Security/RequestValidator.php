<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Security;

use MrKindy\MultiTenantWordPress\Domain\DomainValidator;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;

final readonly class RequestValidator
{
    /**
     * @param list<string> $trustedDomainSuffixes
     */
    public function __construct(
        private DomainValidator $domainValidator,
        private array $trustedDomainSuffixes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $server
     */
    public function validate(array $server): string
    {
        $host = $server['HTTP_HOST'] ?? null;

        if (!is_string($host)) {
            throw new InvalidDomainException();
        }

        $domain = $this->domainValidator->normalizeAndValidate($host);

        if ($this->trustedDomainSuffixes !== [] && !$this->isTrusted($domain)) {
            throw new InvalidDomainException();
        }

        return $domain;
    }

    private function isTrusted(string $domain): bool
    {
        foreach ($this->trustedDomainSuffixes as $configuredSuffix) {
            $suffix = strtolower(rtrim(trim($configuredSuffix), '.'));

            if ($suffix === '') {
                throw new ConfigurationException('Trusted domain suffix cannot be empty.');
            }

            if (str_starts_with($suffix, '*.')) {
                $suffix = substr($suffix, 2);

                if ($suffix !== '' && str_ends_with($domain, '.' . $suffix)) {
                    return true;
                }

                continue;
            }

            if ($domain === $suffix || str_ends_with($domain, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }
}
