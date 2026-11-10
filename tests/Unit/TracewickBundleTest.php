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
    public function testBuildRegistersMessengerMiddlewarePassBeforeMessengerPass(): void
    {
        $container = new ContainerBuilder();
        (new TracewickBundle())->build($container);

        $passConfig = $container->getCompilerPassConfig();
        $reflection = new \ReflectionProperty($passConfig, 'beforeOptimizationPasses');
        /** @var array<int, list<object>> $byPriority */
        $byPriority = $reflection->getValue($passConfig);

        $priority = null;
        foreach ($byPriority as $passPriority => $group) {
            foreach ($group as $pass) {
                if ($pass instanceof AddTracewickMessengerMiddlewarePass) {
                    $priority = $passPriority;
                }
            }
        }

        self::assertSame(10, $priority);
    }
}
