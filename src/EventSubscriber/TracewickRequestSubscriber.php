<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * Profiles Symfony HTTP main requests with Tracewick (Demo lifecycle, DI-wired).
 *
 * Starts before the router, labels the route after the router matches, and delivers on
 * kernel.terminate after the response has been sent. Fail-open: errors are swallowed at the
 * listener boundary.
 */
final readonly class TracewickRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HttpProfiler $profiler,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['startProfile', 4096],
                // The router listener runs at priority 32.
                ['labelRoute', 31],
            ],
            KernelEvents::TERMINATE => ['deliverProfile', -4096],
        ];
    }

    public function startProfile(RequestEvent $event): void
    {
        try {
            if (!$event->isMainRequest()) {
                return;
            }

            $this->profiler->start([
                'route' => 'unmatched',
                'method' => $event->getRequest()->getMethod(),
            ]);
        } catch (\Throwable) {
            // Fail-open: never break the request.
        }
    }

    public function labelRoute(RequestEvent $event): void
    {
        try {
            $route = $event->getRequest()->attributes->get('_route');
            if ($event->isMainRequest() && \is_string($route)) {
                $this->profiler->label('route', $route);
            }
        } catch (\Throwable) {
            // Fail-open.
        }
    }

    public function deliverProfile(TerminateEvent $event): void
    {
        try {
            $this->profiler->stop();
            $this->profiler->flush();
        } catch (\Throwable) {
            // Fail-open.
        }
    }
}
