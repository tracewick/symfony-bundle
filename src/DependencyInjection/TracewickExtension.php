<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * Loads `tracewick` bundle configuration into the container.
 */
final class TracewickExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->processConfiguration(new Configuration(), $configs);
    }
}
