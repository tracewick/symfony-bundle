<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickCommandSubscriber;
use Tracewick\Bridge\Symfony\Middleware\TracewickMessengerMiddleware;
use Tracewick\Sink\ApplicationName;

/**
 * Loads `tracewick` bundle configuration into the container.
 *
 * Invalid configuration fails at container build so request handling never sees a half-wired profiler.
 * Concrete application_name values are checked against Tracewick's ApplicationName rules; `%…%`
 * placeholders are deferred until the container resolves them.
 */
final class TracewickExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{
         *     enabled: bool,
         *     application_name: ?string,
         *     sample_rate: float,
         *     spool_directory: ?string,
         *     sink: string,
         *     label_controller: bool,
         *     profile_console: bool,
         *     profile_messenger: bool,
         *     base_labels: array<string, string>,
         *     pyroscope: array{
         *         server_url: ?string,
         *         basic_auth_user: ?string,
         *         basic_auth_password: ?string,
         *         tenant: ?string
         *     }
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $configs);

        if (
            $config['enabled']
            && \is_string($config['application_name'])
            && !str_contains($config['application_name'], '%')
        ) {
            try {
                ApplicationName::assertValid($config['application_name']);
            } catch (\Throwable $e) {
                throw new InvalidConfigurationException(
                    \sprintf('Invalid tracewick.application_name: %s', $e->getMessage()),
                    0,
                    $e,
                );
            }
        }

        $container->setParameter('tracewick.config', $config);
        $container->setParameter('tracewick.enabled', $config['enabled']);
        $container->setParameter('tracewick.label_controller', $config['label_controller']);
        $container->setParameter('tracewick.profile_console', $config['profile_console']);
        $container->setParameter('tracewick.profile_messenger', $config['profile_messenger']);

        if (!$config['enabled']) {
            return;
        }

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        if (!$config['profile_console']) {
            $container->removeDefinition(TracewickCommandSubscriber::class);
        }

        // Soft dependency: skip when Messenger is not installed (fail-open).
        if (
            !$config['profile_messenger']
            || !interface_exists(\Symfony\Component\Messenger\Middleware\MiddlewareInterface::class)
        ) {
            $container->removeDefinition(TracewickMessengerMiddleware::class);
        }
    }
}
