<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tracewick\Bridge\Symfony\Profiler\UnitOfWorkHttpProfiler;
use Tracewick\Bridge\Symfony\Tests\Support\RecordingSink;
use Tracewick\Bridge\Symfony\Tests\Support\ScriptedSampler;
use Tracewick\Profile\Labels;
use Tracewick\Runtime\ProfilerConfig;
use Tracewick\Runtime\UnitOfWorkProfiler;

#[CoversClass(UnitOfWorkHttpProfiler::class)]
final class UnitOfWorkHttpProfilerTest extends TestCase
{
    public function testDelegatesLifecycleToUnitOfWorkProfiler(): void
    {
        $sink = new RecordingSink();
        $sampler = (new ScriptedSampler())->willSample('App\\Controller::home');
        $profiler = new UnitOfWorkProfiler(
            $sampler,
            $sink,
            new ProfilerConfig(new Labels(['env' => 'test']), sampleRate: 1.0),
            random: static fn(): float => 0.0,
        );
        $adapter = new UnitOfWorkHttpProfiler($profiler);

        $adapter->start(['route' => 'unmatched', 'method' => 'GET']);
        $adapter->label('route', 'app_home');
        $adapter->stop();
        $adapter->flush();

        self::assertCount(1, $sink->sent);
        self::assertSame('app_home', $sink->sent[0]->labels->get('route'));
        self::assertSame('GET', $sink->sent[0]->labels->get('method'));
        self::assertSame(1, $adapter->diagnostics()->collectedProfiles);
        self::assertSame(1, $adapter->diagnostics()->deliveredProfiles);
    }
}
