<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Profiler;

/**
 * Narrow HTTP unit-of-work surface for the Symfony subscriber (testable without ext-excimer).
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
}
