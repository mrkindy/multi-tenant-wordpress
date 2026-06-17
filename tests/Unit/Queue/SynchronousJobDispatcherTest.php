<?php

declare(strict_types=1);

namespace MrKindy\MultiTenantWordPress\Tests\Unit\Queue;

use MrKindy\MultiTenantWordPress\Queue\SynchronousJobDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SynchronousJobDispatcherTest extends TestCase
{
    public function testItLogsJobDispatch(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = new SynchronousJobDispatcher($logger);

        $job = new \stdClass();

        $logger->expects(self::once())
            ->method('debug')
            ->with(
                self::stringContains('Job dispatched'),
                self::arrayHasKey('job_class'),
            );

        $dispatcher->dispatch($job);
    }

    public function testItAcceptsAnyJobObject(): void
    {
        $dispatcher = new SynchronousJobDispatcher();

        $job = new \stdClass();

        // Should not throw
        $dispatcher->dispatch($job);

        self::assertTrue(true); // Test passes if no exception
    }

    public function testItWorksWithNullLogger(): void
    {
        $dispatcher = new SynchronousJobDispatcher();

        $job = new \stdClass();

        // Should not throw
        $dispatcher->dispatch($job);

        self::assertTrue(true); // Test passes if no exception
    }
}
