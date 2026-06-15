<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Security;

use MrKindy\MultiTenantWordPress\Domain\DomainValidator;
use MrKindy\MultiTenantWordPress\Exceptions\ConfigurationException;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;
use MrKindy\MultiTenantWordPress\Security\RequestValidator;
use PHPUnit\Framework\TestCase;

final class RequestValidatorTest extends TestCase
{
    public function testItAcceptsTrustedWildcardSuffix(): void
    {
        $validator = new RequestValidator(
            new DomainValidator(),
            ['*.example.com'],
        );

        self::assertSame(
            'shop.example.com',
            $validator->validate(['HTTP_HOST' => 'SHOP.EXAMPLE.COM:443']),
        );
    }

    public function testWildcardDoesNotTrustApexDomain(): void
    {
        $validator = new RequestValidator(
            new DomainValidator(),
            ['*.example.com'],
        );

        $this->expectException(InvalidDomainException::class);

        $validator->validate(['HTTP_HOST' => 'example.com']);
    }

    public function testItRejectsMissingHost(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new RequestValidator(new DomainValidator()))->validate([]);
    }

    public function testItRejectsUntrustedDomain(): void
    {
        $validator = new RequestValidator(
            new DomainValidator(),
            ['*.example.com'],
        );

        $this->expectException(InvalidDomainException::class);

        $validator->validate(['HTTP_HOST' => 'attacker.test']);
    }

    public function testLiteralSuffixTrustsApexAndSubdomains(): void
    {
        $validator = new RequestValidator(
            new DomainValidator(),
            ['example.com'],
        );

        self::assertSame(
            'example.com',
            $validator->validate(['HTTP_HOST' => 'example.com']),
        );
        self::assertSame(
            'shop.example.com',
            $validator->validate(['HTTP_HOST' => 'shop.example.com']),
        );
    }

    public function testItRejectsEmptyTrustedSuffixConfiguration(): void
    {
        $validator = new RequestValidator(
            new DomainValidator(),
            [' '],
        );

        $this->expectException(ConfigurationException::class);

        $validator->validate(['HTTP_HOST' => 'shop.example.com']);
    }
}
