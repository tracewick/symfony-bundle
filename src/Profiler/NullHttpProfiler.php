<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Profiler;

/**
 * No-op profiler used when wiring is disabled or construction fails (fail-open).
 */
final class NullHttpProfiler implements HttpProfiler
{
    public function start(array $labels = []): void {}

    public function label(string $key, string $value): void {}

    public function stop(): void {}

    public function flush(): void {}
}
