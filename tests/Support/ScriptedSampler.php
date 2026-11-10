<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Support;

use Tracewick\Sampler\EventType;
use Tracewick\Sampler\Frame;
use Tracewick\Sampler\Sample;
use Tracewick\Sampler\SampleBatch;
use Tracewick\Sampler\Sampler;

/**
 * Minimal sampler that yields one scripted batch while running (for adapter tests without ext-excimer).
 */
final class ScriptedSampler implements Sampler
{
    /** @var list<SampleBatch> */
    private array $batches = [];

    private bool $running = false;

    private bool $ranSinceFlush = false;

    public function willSample(string $function): self
    {
        $this->batches[] = new SampleBatch(
            EventType::Wall,
            10_000_000,
            1_000,
            2_000,
            [new Sample([new Frame($function)], 1, 1_500)],
        );

        return $this;
    }

    public function start(): void
    {
        $this->running = true;
        $this->ranSinceFlush = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function flush(): SampleBatch
    {
        $ran = $this->ranSinceFlush;
        $this->ranSinceFlush = $this->running;
        $batch = $ran ? array_shift($this->batches) : null;

        return $batch ?? new SampleBatch(EventType::Wall, 10_000_000, 1_000, 2_000);
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function failureCount(): int
    {
        return 0;
    }

    public function lastFailure(): ?\Throwable
    {
        return null;
    }
}
