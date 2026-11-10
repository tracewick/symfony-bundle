<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

$config = new Configuration();

return $config
    ->addPathsToScan([__DIR__ . '/src'], isDev: false)
    ->addPathsToScan([__DIR__ . '/tests'], isDev: true);
