<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for the `tracewick` root key.
 *
 * Keys are filled in as the HTTP wiring lands; the empty root keeps the container bootable.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tracewick');
        $treeBuilder->getRootNode();

        return $treeBuilder;
    }
}
