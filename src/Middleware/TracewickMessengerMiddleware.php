<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * Profiles one consumed Messenger message as a Tracewick unit of work (opt-in).
 *
 * Only envelopes with {@see ReceivedStamp} are profiled (worker consume path), so sync
 * dispatch inside an HTTP request does not rotate the request profile. Label is the message
 * FQCN (`message`), never the payload. Fail-open at the middleware boundary.
 */
final readonly class TracewickMessengerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private HttpProfiler $profiler,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (!$envelope->last(ReceivedStamp::class) instanceof ReceivedStamp) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            $this->profiler->start([
                'message' => $envelope->getMessage()::class,
            ]);
        } catch (\Throwable) {
            // Fail-open: never break consumption.
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            try {
                $this->profiler->stop();
                $this->profiler->flush();
            } catch (\Throwable) {
                // Fail-open.
            }
        }
    }
}
