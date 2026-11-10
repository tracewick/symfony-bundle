<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\DependencyInjection\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Tracewick\Bridge\Symfony\DependencyInjection\Compiler\AddTracewickMessengerMiddlewarePass;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;
use Tracewick\Bridge\Symfony\Profiler\NullHttpProfiler;
use Tracewick\Bridge\Symfony\TracewickBundle;

#[CoversClass(AddTracewickMessengerMiddlewarePass::class)]
#[CoversClass(TracewickBundle::class)]
final class AddTracewickMessengerMiddlewarePassTest extends TestCase
{
    public function testPrependsMiddlewareOntoTaggedBusAndPreservesArguments(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.default']],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => [true]],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.default']],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => [true]],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testSkipsWhenDefinitionMissing(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
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
        $this->registerTracewickMiddleware($container);
        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message', 'arguments' => [true]],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message', 'arguments' => [true]],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testIgnoresUntaggedMiddlewareParameters(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $container->setParameter('app.http.middleware', [
            ['id' => 'send_message', 'arguments' => ['keep-me']],
        ]);
        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'send_message'],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => 'send_message', 'arguments' => ['keep-me']],
        ], $container->getParameter('app.http.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message'],
        ], $container->getParameter('messenger.bus.default.middleware'));
    }

    public function testPrependsOntoMultipleTaggedBuses(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $container->setDefinition('messenger.bus.a', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setDefinition('messenger.bus.b', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.a.middleware', [
            ['id' => 'send_message', 'arguments' => [false]],
        ]);
        $container->setParameter('messenger.bus.b.middleware', [
            ['id' => 'handle_message', 'arguments' => [true]],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message', 'arguments' => [false]],
        ], $container->getParameter('messenger.bus.a.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'handle_message', 'arguments' => [true]],
        ], $container->getParameter('messenger.bus.b.middleware'));
    }

    public function testContinuesAfterAlreadyRegisteredBusInsteadOfBreaking(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $container->setDefinition('messenger.bus.a', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setDefinition('messenger.bus.b', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.a.middleware', [
            ['id' => TracewickMessengerMiddleware::class],
        ]);
        $container->setParameter('messenger.bus.b.middleware', [
            ['id' => 'send_message', 'arguments' => [true]],
        ]);

        (new AddTracewickMessengerMiddlewarePass())->process($container);

        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
        ], $container->getParameter('messenger.bus.a.middleware'));
        self::assertSame([
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'send_message', 'arguments' => [true]],
        ], $container->getParameter('messenger.bus.b.middleware'));
    }

    public function testRunsBeforeMessengerPassAndKeepsBusNameArguments(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $this->registerAbstractMessengerMiddleware($container);

        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setPublic(true)
            ->setArguments([[]])
            ->addTag('messenger.bus'));
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.default']],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => [true]],
        ]);

        // Same order as production: Tracewick pass (priority 10) then MessengerPass (0).
        (new AddTracewickMessengerMiddlewarePass())->process($container);
        (new MessengerPass())->process($container);

        self::assertFalse($container->hasParameter('messenger.bus.default.middleware'));

        $busDefinition = $container->getDefinition('messenger.bus.default');
        $middlewareArg = $busDefinition->getArgument(0);
        self::assertInstanceOf(IteratorArgument::class, $middlewareArg);

        /** @var list<Reference> $refs */
        $refs = $middlewareArg->getValues();
        self::assertNotEmpty($refs);
        self::assertSame(TracewickMessengerMiddleware::class, (string) $refs[0]);

        $busNameMiddleware = $container->getDefinition('messenger.bus.default.middleware.add_bus_name_stamp_middleware');
        self::assertInstanceOf(ChildDefinition::class, $busNameMiddleware);
        self::assertSame(['messenger.bus.default'], $busNameMiddleware->getArguments());

        $sendMessage = $container->getDefinition('messenger.bus.default.middleware.send_message');
        self::assertInstanceOf(ChildDefinition::class, $sendMessage);
        self::assertSame([true], $sendMessage->getArguments());

        // MessengerPass later replaceArgument(0, handlers_locator) on handle_message; the important
        // regression is that our pass did not strip sibling middleware arguments before that.
        self::assertTrue($container->hasDefinition('messenger.bus.default.middleware.handle_message'));
    }

    public function testStrippingArgumentsWouldLoseBusName(): void
    {
        $container = new ContainerBuilder();
        $this->registerTracewickMiddleware($container);
        $this->registerAbstractMessengerMiddleware($container);
        $container->setDefinition('messenger.bus.default', (new Definition(MessageBus::class))
            ->setArguments([[]])
            ->addTag('messenger.bus'));

        // Simulate the old buggy normalize that kept only id.
        $container->setParameter('messenger.bus.default.middleware', [
            ['id' => TracewickMessengerMiddleware::class],
            ['id' => 'add_bus_name_stamp_middleware'],
            ['id' => 'send_message'],
            ['id' => 'handle_message'],
        ]);

        (new MessengerPass())->process($container);

        $busNameMiddleware = $container->getDefinition('messenger.bus.default.middleware.add_bus_name_stamp_middleware');
        self::assertInstanceOf(ChildDefinition::class, $busNameMiddleware);
        self::assertSame([], $busNameMiddleware->getArguments());
    }

    private function registerTracewickMiddleware(ContainerBuilder $container): void
    {
        $container->register('tracewick.profiler', NullHttpProfiler::class);
        $container->register(TracewickMessengerMiddleware::class, TracewickMessengerMiddleware::class)
            ->setArguments([new Reference('tracewick.profiler')]);
    }

    private function registerAbstractMessengerMiddleware(ContainerBuilder $container): void
    {
        $container->register('messenger.senders_locator', \stdClass::class);

        $container->register('messenger.middleware.add_bus_name_stamp_middleware', AddBusNameStampMiddleware::class)
            ->setAbstract(true);

        $container->register('messenger.middleware.send_message', SendMessageMiddleware::class)
            ->setAbstract(true)
            ->setArguments([new Reference('messenger.senders_locator'), null]);

        $container->register('messenger.middleware.handle_message', HandleMessageMiddleware::class)
            ->setAbstract(true)
            ->setArguments([new Reference('messenger.senders_locator'), false]);
    }
}
