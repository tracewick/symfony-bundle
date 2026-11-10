<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tracewick\Bridge\Symfony\DependencyInjection\Compiler\AddTracewickMessengerMiddlewarePass;

/**
 * Official Symfony bridge for Tracewick.
 *
 * Uses Tracewick Runtime and Sink APIs only; the core library stays framework-agnostic.
 */
final class TracewickBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Priority 10: run before FrameworkBundle's MessengerPass (priority 0), which consumes
        // and removes `{busId}.middleware` parameters.
        $container->addCompilerPass(
            new AddTracewickMessengerMiddlewarePass(),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
            10,
        );
    }
}
