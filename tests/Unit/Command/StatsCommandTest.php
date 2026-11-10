<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tracewick\Bridge\Symfony\Command\StatsCommand;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;
use Tracewick\Runtime\Diagnostics;
use Tracewick\Sampler\NullSamplerReason;

#[CoversClass(StatsCommand::class)]
final class StatsCommandTest extends TestCase
{
    public function testExecutePrintsDiagnosticsTable(): void
    {
        $profiler = $this->createMock(HttpProfiler::class);
        $profiler->method('diagnostics')->willReturn(new Diagnostics(
            collectedProfiles: 3,
            droppedProfiles: 1,
            deliveredProfiles: 2,
            rejectedProfiles: 0,
            failedProfiles: 0,
            skippedProfiles: 1,
            emptyProfiles: 0,
            swallowedErrors: 0,
            inactiveSampler: NullSamplerReason::ExtensionMissing,
            samplerFailures: 0,
        ));

        $tester = new CommandTester(new StatsCommand($profiler));
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Metric', $display);
        self::assertStringContainsString('Value', $display);
        foreach ([
            'collected_profiles',
            'dropped_profiles',
            'delivered_profiles',
            'rejected_profiles',
            'failed_profiles',
            'skipped_profiles',
            'empty_profiles',
            'swallowed_errors',
            'sampler_failures',
            'inactive_sampler',
        ] as $metric) {
            self::assertStringContainsString($metric, $display);
        }
        self::assertMatchesRegularExpression('/collected_profiles\s+3\b/', $display);
        self::assertMatchesRegularExpression('/dropped_profiles\s+1\b/', $display);
        self::assertMatchesRegularExpression('/delivered_profiles\s+2\b/', $display);
        self::assertMatchesRegularExpression('/skipped_profiles\s+1\b/', $display);
        self::assertMatchesRegularExpression('/inactive_sampler\s+extension_missing\b/', $display);
    }

    public function testDefaultNameIsTracewickStats(): void
    {
        $profiler = $this->createMock(HttpProfiler::class);
        $command = new StatsCommand($profiler);

        self::assertSame('tracewick:stats', $command->getName());
    }
}
