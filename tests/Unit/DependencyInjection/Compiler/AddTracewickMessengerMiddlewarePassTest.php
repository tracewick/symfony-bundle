<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Tracewick\Bridge\Symfony\DependencyInjection\Compiler\AddTracewickMessengerMiddlewarePass;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;

#[CoversClass(AddTracewickMessengerMiddlewarePass::class)]
final class AddTracewickMessengerMiddlewarePassTest extends TestCase
{
    public function testPrependsMiddlewareOntoBusParameter(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testSkipsWhenDefinitionMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testDoesNotDuplicateExistingEntry(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testIgnoresUnrelatedMiddlewareParameters(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('app.http.middleware', ['not', 'bus', 'shape']);
        $container->setParameter('app.scalar.middleware', 'not-an-array');
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame(['not', 'bus', 'shape'], $container->getParameter('app.http.middleware'));
        self::assertSame('not-an-array', $container->getParameter('app.scalar.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testContinuesAfterMalformedBusList(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('messenger.bus.broken.middleware', [
            ['missing_id' => true],
        ]);
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['missing_id' => true],
        ], $container->getParameter('messenger.bus.broken.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testAcceptsEmptyBusMiddlewareList(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('messenger.bus.default.middleware', []);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testContinuesAfterAlreadyRegisteredBus(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(TracewickMessengerMiddleware::class, new Definition(TracewickMessengerMiddleware::class));
        $container->setParameter('messenger.bus.a.middleware', [
            ['id' => TracewickMessengerMiddleware::class],
        ]);
        $container->setParameter('messenger.bus.b.middleware', [
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
        ], $container->getParameter('messenger.bus.a.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.b.middleware'));
    }
}
