<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Support;

use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * Records HTTP profiler calls for functional Kernel lifecycle assertions.
 */
final class RecordingHttpProfiler implements HttpProfiler
{
    /** @var list<array{0: 'start'|'label'|'stop'|'flush', 1?: array<string, string>|string, 2?: string}> */
    public array $calls = [];

    public function start(array $labels = []): void
    {
        $this->calls[] = ['start', $labels];
    }

    public function label(string $key, string $value): void
    {
        $this->calls[] = ['label', $key, $value];
    }

    public function stop(): void
    {
        $this->calls[] = ['stop'];
    }

    public function flush(): void
    {
        $this->calls[] = ['flush'];
    }
}
