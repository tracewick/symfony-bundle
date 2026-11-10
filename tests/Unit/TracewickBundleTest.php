<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tracewick\Bridge\Symfony\DependencyInjection\Compiler\AddTracewickMessengerMiddlewarePass;
use Tracewick\Bridge\Symfony\TracewickBundle;

#[CoversClass(TracewickBundle::class)]
final class TracewickBundleTest extends TestCase
{
    public function testBuildRegistersMessengerMiddlewarePass(): void
    {
        $container = new ContainerBuilder();
        (new TracewickBundle())->build($container);

        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();
        $found = false;
        foreach ($passes as $pass) {
            if ($pass instanceof AddTracewickMessengerMiddlewarePass) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }
}
