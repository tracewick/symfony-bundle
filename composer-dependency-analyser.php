<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->addPathsToScan([__DIR__ . '/src'], isDev: false)
    ->addPathsToScan([__DIR__ . '/tests'], isDev: true)
    // Wired when the HTTP subscriber / profiler factory lands (M2).
    ->ignoreErrorsOnPackage('tracewick/tracewick', [ErrorType::UNUSED_DEPENDENCY]);
