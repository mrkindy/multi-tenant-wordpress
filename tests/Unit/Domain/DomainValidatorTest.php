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

    public function testItRejectsEmptyString(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('');
    }

    public function testItRejectsWhitespaceOnly(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('   ');
    }

    public function testItRejectsNullByteInjection(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate("example.com\x00attacker.com");
    }

    public function testItRejectsControlCharacters(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate("example.com\x01\x02");
    }

    public function testItRejectsDelCharacter(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate("example.com\x7f");
    }

    public function testItRejectsDoubleColonInPort(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com::8080');
    }

    public function testItRejectsEmptyPort(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com:');
    }

    public function testItRejectsPortZero(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com:0');
    }

    public function testItRejectsPortTooHigh(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com:65536');
    }

    public function testItRejectsNegativePort(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com:-1');
    }

    public function testItRejectsNonNumericPort(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example.com:abc');
    }

    public function testItRejectsDomainTooLong(): void
    {
        $this->expectException(InvalidDomainException::class);

        // Create a domain longer than 253 characters
        $longDomain = str_repeat('a', 250) . '.com';
        (new DomainValidator())->normalizeAndValidate($longDomain);
    }

    public function testItRejectsInvalidDomainCharacters(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('test@example.com');
    }

    public function testItRejectsDomainWithSpace(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate('example .com');
    }

    public function testItRejectsDomainWithTab(): void
    {
        $this->expectException(InvalidDomainException::class);

        (new DomainValidator())->normalizeAndValidate("example\t.com");
    }

    public function testItAcceptsSubdomainWithHyphen(): void
    {
        self::assertSame(
            'my-shop.example.com',
            (new DomainValidator())->normalizeAndValidate('my-shop.example.com'),
        );
    }

    public function testItAcceptsSubdomainWithNumbers(): void
    {
        self::assertSame(
            'shop123.example.com',
            (new DomainValidator())->normalizeAndValidate('shop123.example.com'),
        );
    }

    public function testItAcceptsDomainStartingWithNumber(): void
    {
        self::assertSame(
            '123example.com',
            (new DomainValidator())->normalizeAndValidate('123example.com'),
        );
    }

    public function testItAcceptsSingleLabelDomain(): void
    {
        self::assertSame(
            'localhost',
            (new DomainValidator(true))->normalizeAndValidate('localhost'),
        );
    }

    public function testItAcceptsPort80(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com:80'),
        );
    }

    public function testItAcceptsPort443(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com:443'),
        );
    }

    public function testItAcceptsPort8080(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com:8080'),
        );
    }

    public function testItAcceptsPort65535(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com:65535'),
        );
    }

    public function testItAcceptsDomainWithTrailingDot(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com.'),
        );
    }

    public function testItAcceptsDomainWithMultipleTrailingDots(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('example.com...'),
        );
    }

    public function testItAcceptsUppercaseLetters(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('EXAMPLE.COM'),
        );
    }

    public function testItAcceptsMixedCase(): void
    {
        self::assertSame(
            'example.com',
            (new DomainValidator())->normalizeAndValidate('ExAmPlE.CoM'),
        );
    }
}
