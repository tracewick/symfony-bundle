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
        private bool $labelController = false,
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
            if (!$event->isMainRequest()) {
                return;
            }

            $request = $event->getRequest();
            $route = $request->attributes->get('_route');
            if (\is_string($route)) {
                $this->profiler->label('route', $route);
            }

            if ($this->labelController) {
                $controller = $this->controllerLabel($request->attributes->get('_controller'));
                if (null !== $controller) {
                    $this->profiler->label('controller', $controller);
                }
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

    /**
     * Turns Symfony's `_controller` attribute into a low-cardinality series label, or null to skip.
     */
    private function controllerLabel(mixed $controller): ?string
    {
        if (\is_string($controller) && '' !== $controller) {
            return $controller;
        }

        if (\is_array($controller) && isset($controller[0], $controller[1]) && \is_string($controller[1])) {
            $class = $controller[0];
            if (\is_object($class)) {
                return $class::class . '::' . $controller[1];
            }
            if (\is_string($class) && '' !== $class) {
                return $class . '::' . $controller[1];
            }
        }

        return null;
    }
}
