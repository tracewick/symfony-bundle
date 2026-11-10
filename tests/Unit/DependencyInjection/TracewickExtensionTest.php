<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tracewick\Bridge\Symfony\DependencyInjection\Configuration;
use Tracewick\Bridge\Symfony\DependencyInjection\TracewickExtension;

#[CoversClass(TracewickExtension::class)]
#[CoversClass(Configuration::class)]
final class TracewickExtensionTest extends TestCase
{
    public function testLoadAcceptsEmptyConfig(): void
    {
        $extension = new TracewickExtension();
        $container = new ContainerBuilder();

        $extension->load([], $container);

        self::assertFalse($container->hasParameter('tracewick'));
    }
}
