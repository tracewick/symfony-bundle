<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * Prints Tracewick process profiler counters (analogous to messenger:stats).
 */
#[AsCommand(
    name: 'tracewick:stats',
    description: 'Show Tracewick profiler diagnostics for this process',
)]
final class StatsCommand extends Command
{
    public function __construct(
        private readonly HttpProfiler $profiler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $diagnostics = $this->profiler->diagnostics();

        $io->table(
            ['Metric', 'Value'],
            [
                ['collected_profiles', (string) $diagnostics->collectedProfiles],
                ['dropped_profiles', (string) $diagnostics->droppedProfiles],
                ['delivered_profiles', (string) $diagnostics->deliveredProfiles],
                ['rejected_profiles', (string) $diagnostics->rejectedProfiles],
                ['failed_profiles', (string) $diagnostics->failedProfiles],
                ['skipped_profiles', (string) $diagnostics->skippedProfiles],
                ['empty_profiles', (string) $diagnostics->emptyProfiles],
                ['swallowed_errors', (string) $diagnostics->swallowedErrors],
                ['sampler_failures', (string) $diagnostics->samplerFailures],
                [
                    'inactive_sampler',
                    $diagnostics->inactiveSampler->value ?? '—',
                ],
            ],
        );

        return Command::SUCCESS;
    }
}
