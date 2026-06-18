<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Provisioning;

use MrKindy\MultiTenantWordPress\Provisioning\DatabaseNameGenerator;
use PHPUnit\Framework\TestCase;

final class DatabaseNameGeneratorTest extends TestCase
{
    public function testItGeneratesDatabaseNameWithDefaultPrefix(): void
    {
        $generator = new DatabaseNameGenerator();

        $name = $generator->generateDatabaseName('123', 'shop.example.com');

        self::assertSame('tenant_123', $name);
    }

    public function testItGeneratesDatabaseUserWithDefaultPrefix(): void
    {
        $generator = new DatabaseNameGenerator();

        $user = $generator->generateDatabaseUser('123', 'shop.example.com');

        self::assertSame('tenant_123_user', $user);
    }

    public function testItGeneratesDatabaseNameWithCustomPrefix(): void
    {
        $generator = new DatabaseNameGenerator('wp_', 'wp_');

        $name = $generator->generateDatabaseName('456', 'blog.example.com');

        self::assertSame('wp_456', $name);
    }

    public function testItGeneratesDatabaseUserWithCustomPrefix(): void
    {
        $generator = new DatabaseNameGenerator('wp_', 'wp_');

        $user = $generator->generateDatabaseUser('456', 'blog.example.com');

        self::assertSame('wp_456_user', $user);
    }

    public function testItSanitizesSpecialCharactersInTenantId(): void
    {
        $generator = new DatabaseNameGenerator();

        $name = $generator->generateDatabaseName('abc-def.123', 'shop.example.com');

        self::assertSame('tenant_abc_def_123', $name);
    }

    public function testItTruncatesDatabaseNameToMaxLength(): void
    {
        $generator = new DatabaseNameGenerator();

        // Create a very long tenant ID
        $longId = str_repeat('a', 100);
        $name = $generator->generateDatabaseName($longId, 'shop.example.com');

        self::assertLessThanOrEqual(64, strlen($name));
    }

    public function testItTruncatesDatabaseUserToMaxLength(): void
    {
        $generator = new DatabaseNameGenerator();

        // Create a very long tenant ID
        $longId = str_repeat('a', 100);
        $user = $generator->generateDatabaseUser($longId, 'shop.example.com');

        self::assertLessThanOrEqual(32, strlen($user));
        // Should still end with _user
        self::assertStringEndsWith('_user', $user);
    }

    public function testItPreservesUserSuffixWhenTruncating(): void
    {
        $generator = new DatabaseNameGenerator('very_long_prefix_', 'very_long_prefix_');

        $user = $generator->generateDatabaseUser('123', 'shop.example.com');

        self::assertLessThanOrEqual(32, strlen($user));
        self::assertStringEndsWith('_user', $user);
    }

    public function testItHandlesNumericTenantId(): void
    {
        $generator = new DatabaseNameGenerator();

        $name = $generator->generateDatabaseName('42', 'shop.example.com');
        $user = $generator->generateDatabaseUser('42', 'shop.example.com');

        self::assertSame('tenant_42', $name);
        self::assertSame('tenant_42_user', $user);
    }

    public function testItIgnoresDomainParameter(): void
    {
        $generator = new DatabaseNameGenerator();

        $name1 = $generator->generateDatabaseName('123', 'shop.example.com');
        $name2 = $generator->generateDatabaseName('123', 'different.example.org');

        self::assertSame($name1, $name2);
    }
}
