<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Profiler;

use Tracewick\Runtime\Diagnostics;

/**
 * Narrow unit-of-work surface for Symfony subscribers/middleware (testable without ext-excimer).
 */
interface HttpProfiler
{
    /**
     * @param array<string, string> $labels
     */
    public function start(array $labels = []): void;

    public function label(string $key, string $value): void;

    public function stop(): void;

    public function flush(): void;

    /**
     * Snapshot of what this process profiler has done so far (see core runtime.md § Diagnostics).
     */
    public function diagnostics(): Diagnostics;
}
