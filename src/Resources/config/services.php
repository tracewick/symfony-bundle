<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tracewick\Bridge\Symfony\Command\StatsCommand;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickCommandSubscriber;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickRequestSubscriber;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;
use Tracewick\Bridge\Symfony\Profiler\ProfilerFactory;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tracewick.profiler_factory', ProfilerFactory::class);

    $services->set('tracewick.profiler', HttpProfiler::class)
        ->factory([service('tracewick.profiler_factory'), '__invoke'])
        ->args([
            param('tracewick.config'),
            param('kernel.project_dir'),
            service('logger')->nullOnInvalid(),
        ]);

    $services->alias(HttpProfiler::class, 'tracewick.profiler');

    $services->set(TracewickRequestSubscriber::class)
        ->args([
            service('tracewick.profiler'),
            param('tracewick.label_controller'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TracewickCommandSubscriber::class)
        ->args([
            service('tracewick.profiler'),
            param('tracewick.profile_messenger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TracewickMessengerMiddleware::class)
        ->args([
            service('tracewick.profiler'),
        ]);

    $services->set(StatsCommand::class)
        ->args([
            service('tracewick.profiler'),
        ])
        ->tag('console.command');
};
