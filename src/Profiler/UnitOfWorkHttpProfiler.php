<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Profiler;

use Tracewick\Runtime\UnitOfWorkProfiler;

/**
 * Adapts {@see UnitOfWorkProfiler} to the bundle's HTTP profiler port.
 */
final readonly class UnitOfWorkHttpProfiler implements HttpProfiler
{
    public function __construct(
        private UnitOfWorkProfiler $profiler,
    ) {}

    public function start(array $labels = []): void
    {
        $this->profiler->start($labels);
    }

    public function label(string $key, string $value): void
    {
        $this->profiler->label($key, $value);
    }

    public function stop(): void
    {
        $this->profiler->stop();
    }

    public function flush(): void
    {
        $this->profiler->flush();
    }
}
