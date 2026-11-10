<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

#[CoversClass(TracewickMessengerMiddleware::class)]
final class TracewickMessengerMiddlewareTest extends TestCase
{
    /** @var MockObject&HttpProfiler */
    private MockObject $profiler;

    private TracewickMessengerMiddleware $middleware;

    protected function setUp(): void
    {
        $this->profiler = $this->createMock(HttpProfiler::class);
        $this->middleware = new TracewickMessengerMiddleware($this->profiler);
    }

    public function testHandleProfilesReceivedMessage(): void
    {
        $message = new \stdClass();
        $envelope = (new Envelope($message))->with(new ReceivedStamp('async'));

        $this->profiler->expects(self::once())
            ->method('start')
            ->with(['message' => $message::class]);
        $this->profiler->expects(self::once())->method('stop');
        $this->profiler->expects(self::once())->method('flush');

        $result = $this->middleware->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
    }

    public function testHandleSkipsWhenNotReceived(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->profiler->expects(self::never())->method('start');
        $this->profiler->expects(self::never())->method('stop');
        $this->profiler->expects(self::never())->method('flush');

        $this->middleware->handle($envelope, $this->stackReturning($envelope));
    }

    public function testHandleStopsAndFlushesWhenNextThrows(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new ReceivedStamp('async'));

        $this->profiler->expects(self::once())->method('start');
        $this->profiler->expects(self::once())->method('stop');
        $this->profiler->expects(self::once())->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler boom');

        $this->middleware->handle($envelope, $this->stackThrowing(new \RuntimeException('handler boom')));
    }

    public function testHandleSwallowsStartErrors(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new ReceivedStamp('async'));
        $this->profiler->method('start')->willThrowException(new \RuntimeException('start boom'));
        $this->profiler->expects(self::once())->method('stop');
        $this->profiler->expects(self::once())->method('flush');

        $result = $this->middleware->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
    }

    public function testHandleSwallowsStopErrors(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new ReceivedStamp('async'));
        $this->profiler->expects(self::once())->method('start');
        $this->profiler->method('stop')->willThrowException(new \RuntimeException('stop boom'));

        $result = $this->middleware->handle($envelope, $this->stackReturning($envelope));

        self::assertSame($envelope, $result);
    }

    private function stackReturning(Envelope $envelope): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->method('handle')->willReturn($envelope);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }

    private function stackThrowing(\Throwable $error): StackInterface
    {
        $next = $this->createMock(MiddlewareInterface::class);
        $next->method('handle')->willThrowException($error);

        $stack = $this->createMock(StackInterface::class);
        $stack->method('next')->willReturn($next);

        return $stack;
    }
}
