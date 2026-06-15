<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Domain;

use MrKindy\MultiTenantWordPress\Domain\DomainValidator;
use MrKindy\MultiTenantWordPress\Exceptions\InvalidDomainException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DomainValidatorTest extends TestCase
{
    #[DataProvider('validHosts')]
    public function testItNormalizesValidHosts(string $host, string $expected): void
    {
        self::assertSame(
            $expected,
            (new DomainValidator())->normalizeAndValidate($host),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function validHosts(): iterable
    {
        yield 'lowercase and trim' => ['  SHOP.Example.COM  ', 'shop.example.com'];
        yield 'remove port' => ['shop.example.com:8443', 'shop.example.com'];
        yield 'remove trailing dot' => ['shop.example.com.', 'shop.example.com'];
        yield 'hyphenated hostname' => ['my-shop.example.com', 'my-shop.example.com'];
    }

    #[DataProvider('invalidHosts')]
    public function testItRejectsInvalidHosts(string $host): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate($host);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHosts(): iterable
    {
        yield 'empty' => [''];
        yield 'localhost' => ['localhost'];
        yield 'ipv4' => ['127.0.0.1'];
        yield 'ipv6' => ['[::1]'];
        yield 'scheme' => ['https://example.com'];
        yield 'path' => ['example.com/path'];
        yield 'userinfo' => ['user@example.com'];
        yield 'invalid port' => ['example.com:99999'];
        yield 'header injection' => ["example.com\r\nX-Test: bad"];
        yield 'underscore' => ['bad_host.example.com'];
    }

    public function testItAllowsLocalhostWhenExplicitlyEnabled(): void
    {
        self::assertSame(
            'localhost',
            (new DomainValidator(true))->normalizeAndValidate('LOCALHOST:8080'),
        );
    }
}
