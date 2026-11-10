<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Profiler;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tracewick\Bridge\Symfony\DependencyInjection\Configuration;
use Tracewick\Profile\Labels;
use Tracewick\Runtime\ProfilerConfig;
use Tracewick\Runtime\UnitOfWorkProfiler;
use Tracewick\Sampler\SamplerConfig;
use Tracewick\Sink\PyroscopeConfig;
use Tracewick\Sink\PyroscopeSink;
use Tracewick\Sink\Sink;
use Tracewick\Sink\SpoolSink;
use Tracewick\Transport\SpoolConfig;

/**
 * Builds one shared {@see UnitOfWorkProfiler} per worker process from bundle config.
 *
 * @phpstan-import-type TracewickConfig from Configuration
 */
final class ProfilerFactory
{
    /**
     * @param TracewickConfig $config
     */
    public function __invoke(array $config, string $projectDir, ?LoggerInterface $logger = null): HttpProfiler
    {
        $logger ??= new NullLogger();

        try {
            $profiler = UnitOfWorkProfiler::create(
                $this->createSink($config, $logger),
                new ProfilerConfig(
                    new Labels($config['base_labels']),
                    sampleRate: $config['sample_rate'],
                ),
                new SamplerConfig(projectRoot: '' !== $projectDir ? $projectDir : null),
                logger: $logger,
            );
            $profiler->registerShutdown();

            return new UnitOfWorkHttpProfiler($profiler);
        } catch (\Throwable $e) {
            $logger->warning('Tracewick profiler could not be created; profiling is disabled for this worker.', [
                'exception' => $e,
            ]);

            return new NullHttpProfiler();
        }
    }

    /**
     * @param TracewickConfig $config
     */
    private function createSink(array $config, LoggerInterface $logger): Sink
    {
        $applicationName = $config['application_name'] ?? '';
        if ('' === $applicationName) {
            throw new \InvalidArgumentException('tracewick.application_name resolved to an empty value.');
        }

        if ('pyroscope' === $config['sink']) {
            $serverUrl = $config['pyroscope']['server_url'] ?? '';
            if ('' === $serverUrl) {
                throw new \InvalidArgumentException('tracewick.pyroscope.server_url resolved to an empty value.');
            }

            return new PyroscopeSink(
                new PyroscopeConfig(
                    $serverUrl,
                    $applicationName,
                    $config['pyroscope']['basic_auth_user'],
                    $config['pyroscope']['basic_auth_password'],
                    $config['pyroscope']['tenant'],
                ),
                logger: $logger,
            );
        }

        $spoolDirectory = $config['spool_directory'] ?? '';
        if ('' === $spoolDirectory) {
            throw new \InvalidArgumentException('tracewick.spool_directory resolved to an empty value.');
        }

        return new SpoolSink($applicationName, new SpoolConfig($spoolDirectory));
    }
}
