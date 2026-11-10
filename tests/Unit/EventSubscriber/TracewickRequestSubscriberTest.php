<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickRequestSubscriber;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

#[CoversClass(TracewickRequestSubscriber::class)]
final class TracewickRequestSubscriberTest extends TestCase
{
    /** @var MockObject&HttpProfiler */
    private MockObject $profiler;

    private TracewickRequestSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->profiler = $this->createMock(HttpProfiler::class);
        $this->subscriber = new TracewickRequestSubscriber($this->profiler, labelController: false);
    }

    public function testSubscribedEventsMatchDemoPriorities(): void
    {
        self::assertSame([
            KernelEvents::REQUEST => [
                ['startProfile', 4096],
                ['labelRoute', 31],
            ],
            KernelEvents::TERMINATE => ['deliverProfile', -4096],
        ], TracewickRequestSubscriber::getSubscribedEvents());
    }

    public function testStartProfileOnMainRequest(): void
    {
        $this->profiler->expects(self::once())
            ->method('start')
            ->with(['route' => 'unmatched', 'method' => 'GET']);

        $this->subscriber->startProfile($this->requestEvent(main: true, method: 'GET'));
    }

    public function testStartProfileSkipsSubRequest(): void
    {
        $this->profiler->expects(self::never())->method('start');

        $this->subscriber->startProfile($this->requestEvent(main: false));
    }

    public function testLabelRouteOnMainRequest(): void
    {
        $this->profiler->expects(self::once())
            ->method('label')
            ->with('route', 'app_home');

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');

        $this->subscriber->labelRoute($event);
    }

    public function testLabelRouteSkipsControllerWhenDisabled(): void
    {
        $this->profiler->expects(self::once())
            ->method('label')
            ->with('route', 'app_home');

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');
        $event->getRequest()->attributes->set('_controller', 'App\\Controller\\HomeController::index');

        $this->subscriber->labelRoute($event);
    }

    public function testLabelRouteAlsoLabelsControllerWhenEnabled(): void
    {
        $subscriber = new TracewickRequestSubscriber($this->profiler, labelController: true);
        $calls = [];
        $this->profiler->expects(self::exactly(2))
            ->method('label')
            ->willReturnCallback(static function (string $key, string $value) use (&$calls): void {
                $calls[] = [$key, $value];
            });

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');
        $event->getRequest()->attributes->set('_controller', 'App\\Controller\\HomeController::index');

        $subscriber->labelRoute($event);

        self::assertSame([
            ['route', 'app_home'],
            ['controller', 'App\\Controller\\HomeController::index'],
        ], $calls);
    }

    public function testLabelRouteFormatsArrayController(): void
    {
        $subscriber = new TracewickRequestSubscriber($this->profiler, labelController: true);
        $calls = [];
        $this->profiler->expects(self::exactly(2))
            ->method('label')
            ->willReturnCallback(static function (string $key, string $value) use (&$calls): void {
                $calls[] = [$key, $value];
            });

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');
        $event->getRequest()->attributes->set('_controller', ['App\\Controller\\HomeController', 'index']);

        $subscriber->labelRoute($event);

        self::assertSame([
            ['route', 'app_home'],
            ['controller', 'App\\Controller\\HomeController::index'],
        ], $calls);
    }

    public function testLabelRouteFormatsObjectController(): void
    {
        $subscriber = new TracewickRequestSubscriber($this->profiler, labelController: true);
        $calls = [];
        $this->profiler->expects(self::exactly(2))
            ->method('label')
            ->willReturnCallback(static function (string $key, string $value) use (&$calls): void {
                $calls[] = [$key, $value];
            });

        $controller = new class {
            public function index(): void {}
        };

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');
        $event->getRequest()->attributes->set('_controller', [$controller, 'index']);

        $subscriber->labelRoute($event);

        self::assertSame([
            ['route', 'app_home'],
            ['controller', $controller::class . '::index'],
        ], $calls);
    }

    public function testLabelRouteSkipsUnrecognizedController(): void
    {
        $subscriber = new TracewickRequestSubscriber($this->profiler, labelController: true);
        $this->profiler->expects(self::once())
            ->method('label')
            ->with('route', 'app_home');

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');
        $event->getRequest()->attributes->set('_controller', ['', 'index']);

        $subscriber->labelRoute($event);
    }

    public function testLabelRouteSkipsMissingRoute(): void
    {
        $this->profiler->expects(self::never())->method('label');

        $this->subscriber->labelRoute($this->requestEvent(main: true));
    }

    public function testLabelRouteSkipsSubRequest(): void
    {
        $this->profiler->expects(self::never())->method('label');

        $event = $this->requestEvent(main: false);
        $event->getRequest()->attributes->set('_route', 'app_home');

        $this->subscriber->labelRoute($event);
    }

    public function testDeliverProfileStopsAndFlushes(): void
    {
        $this->profiler->expects(self::once())->method('stop');
        $this->profiler->expects(self::once())->method('flush');

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, Request::create('/'), new Response());

        $this->subscriber->deliverProfile($event);
    }

    public function testStartProfileSwallowsProfilerErrors(): void
    {
        $this->expectNotToPerformAssertions();
        $this->profiler->method('start')->willThrowException(new \RuntimeException('boom'));

        $this->subscriber->startProfile($this->requestEvent(main: true));
    }

    public function testLabelRouteSwallowsProfilerErrors(): void
    {
        $this->expectNotToPerformAssertions();
        $this->profiler->method('label')->willThrowException(new \RuntimeException('boom'));

        $event = $this->requestEvent(main: true);
        $event->getRequest()->attributes->set('_route', 'app_home');

        $this->subscriber->labelRoute($event);
    }

    public function testDeliverProfileSwallowsProfilerErrors(): void
    {
        $this->expectNotToPerformAssertions();
        $this->profiler->method('stop')->willThrowException(new \RuntimeException('boom'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, Request::create('/'), new Response());

        $this->subscriber->deliverProfile($event);
    }

    private function requestEvent(bool $main, string $method = 'GET'): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/', $method);
        $type = $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $type);
    }
}
