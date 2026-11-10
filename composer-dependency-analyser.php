<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->addPathsToScan([__DIR__ . '/src'], isDev: false)
    ->addPathsToScan([__DIR__ . '/tests'], isDev: true)
    // Soft opt-in: Middleware is only registered when profile_messenger is true and Messenger is installed.
    // Unmatched when messenger is absent locally (polyfill); applied in CI once require-dev is installed.
    ->disableReportingUnmatchedIgnores()
    ->ignoreErrorsOnPackage('symfony/messenger', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
