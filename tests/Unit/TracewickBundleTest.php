<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tracewick\Bridge\Symfony\TracewickBundle;

#[CoversClass(TracewickBundle::class)]
final class TracewickBundleTest extends TestCase
{
    public function testBundleExposesSrcPath(): void
    {
        $bundle = new TracewickBundle();

        self::assertSame(\dirname(__DIR__, 2) . '/src', $bundle->getPath());
    }
}
