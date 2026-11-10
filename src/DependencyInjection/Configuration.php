<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tracewick\Runtime\ProfilerConfig;

/**
 * Configuration tree for the `tracewick` root key.
 *
 * Values may still contain `%env(...)%` / `%parameter%` placeholders at compile time; those are
 * not treated as concrete values for format checks (resolved later by the container).
 *
 * @phpstan-type TracewickConfig array{
 *     enabled: bool,
 *     application_name: string|null,
 *     sample_rate: float,
 *     spool_directory: string|null,
 *     sink: string,
 *     label_controller: bool,
 *     profile_console: bool,
 *     base_labels: array<string, string>,
 *     pyroscope: array{
 *         server_url: string|null,
 *         basic_auth_user: string|null,
 *         basic_auth_password: string|null,
 *         tenant: string|null
 *     }
 * }
 */
final class Configuration implements ConfigurationInterface
{
    private const SINKS = ['spool', 'pyroscope'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tracewick');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->info('Whether the bundle wires profiling. Core kill switches still apply at runtime.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('application_name')
                    ->info('Service / application name for spool and Pyroscope series labels.')
                    ->defaultNull()
                ->end()
                ->floatNode('sample_rate')
                    ->info('Share of main requests to profile (0..1). Bundle default is 0.1; do not copy the Demo smoke value of 1.0.')
                    ->min(0.0)
                    ->max(1.0)
                    ->defaultValue(ProfilerConfig::DEFAULT_SAMPLE_RATE)
                ->end()
                ->scalarNode('spool_directory')
                    ->info('Local spool directory (TRACEWICK_SPOOL_DIR). Required when enabled and sink is spool.')
                    ->defaultNull()
                ->end()
                ->scalarNode('sink')
                    ->info('Delivery sink: "spool" (default, production FPM) or "pyroscope" (experiments / non-FPM).')
                    ->defaultValue('spool')
                ->end()
                ->booleanNode('label_controller')
                    ->info('When true, also set a "controller" label from the resolved _controller attribute (opt-in; route name is always labelled).')
                    ->defaultFalse()
                ->end()
                ->booleanNode('profile_console')
                    ->info('When true, profile Symfony console commands with a "command" label (the command name, never argv). Default false — HTTP only unless opted in.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('base_labels')
                    ->info('Static series labels only (for example env, runtime). Never put raw URLs or user ids here.')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('pyroscope')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('server_url')
                            ->info('Pyroscope / Grafana Cloud Profiles base URL. Required when sink is pyroscope.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('basic_auth_user')->defaultNull()->end()
                        ->scalarNode('basic_auth_password')->defaultNull()->end()
                        ->scalarNode('tenant')
                            ->info('Optional X-Scope-OrgID tenant id.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn(array $v): bool => self::requiresApplicationName($v))
                ->thenInvalid('tracewick.application_name is required when tracewick.enabled is true.')
            ->end()
            ->validate()
                ->ifTrue(static fn(array $v): bool => self::requiresSpoolDirectory($v))
                ->thenInvalid('tracewick.spool_directory is required when tracewick.enabled is true and sink is "spool".')
            ->end()
            ->validate()
                ->ifTrue(static fn(array $v): bool => self::requiresPyroscopeServerUrl($v))
                ->thenInvalid('tracewick.pyroscope.server_url is required when sink is "pyroscope".')
            ->end()
            ->validate()
                ->ifTrue(static fn(array $v): bool => self::hasInvalidSink($v))
                ->thenInvalid('tracewick.sink must be "spool" or "pyroscope".')
            ->end()
            ->validate()
                ->ifTrue(static fn(array $v): bool => self::hasPartialBasicAuth($v))
                ->thenInvalid('tracewick.pyroscope basic authentication needs both basic_auth_user and basic_auth_password.')
            ->end();

        return $treeBuilder;
    }

    /**
     * @param array<mixed> $v
     */
    private static function requiresApplicationName(array $v): bool
    {
        $config = self::shape($v);

        return $config['enabled'] && self::isBlank($config['application_name']);
    }

    /**
     * @param array<mixed> $v
     */
    private static function requiresSpoolDirectory(array $v): bool
    {
        $config = self::shape($v);
        if (!$config['enabled'] || self::isPlaceholder($config['sink'])) {
            return false;
        }

        return 'spool' === $config['sink'] && self::isBlank($config['spool_directory']);
    }

    /**
     * @param array<mixed> $v
     */
    private static function requiresPyroscopeServerUrl(array $v): bool
    {
        $config = self::shape($v);
        if (!$config['enabled'] || self::isPlaceholder($config['sink'])) {
            return false;
        }

        return 'pyroscope' === $config['sink'] && self::isBlank($config['pyroscope']['server_url']);
    }

    /**
     * @param array<mixed> $v
     */
    private static function hasInvalidSink(array $v): bool
    {
        $config = self::shape($v);
        if (self::isPlaceholder($config['sink'])) {
            return false;
        }

        return !\in_array($config['sink'], self::SINKS, true);
    }

    /**
     * @param array<mixed> $v
     */
    private static function hasPartialBasicAuth(array $v): bool
    {
        $config = self::shape($v);
        $user = $config['pyroscope']['basic_auth_user'];
        $password = $config['pyroscope']['basic_auth_password'];

        return (null === $user) !== (null === $password);
    }

    /**
     * @param array<mixed> $v
     *
     * @return TracewickConfig
     */
    private static function shape(array $v): array
    {
        $pyroscope = \is_array($v['pyroscope'] ?? null) ? $v['pyroscope'] : [];
        $baseLabels = [];
        if (\is_array($v['base_labels'] ?? null)) {
            foreach ($v['base_labels'] as $key => $value) {
                if (\is_string($key) && \is_string($value)) {
                    $baseLabels[$key] = $value;
                }
            }
        }

        return [
            'enabled' => \is_bool($v['enabled'] ?? null) ? $v['enabled'] : false,
            'application_name' => self::nullableString($v['application_name'] ?? null),
            'sample_rate' => \is_float($v['sample_rate'] ?? null) || \is_int($v['sample_rate'] ?? null)
                ? (float) $v['sample_rate']
                : ProfilerConfig::DEFAULT_SAMPLE_RATE,
            'spool_directory' => self::nullableString($v['spool_directory'] ?? null),
            'sink' => \is_string($v['sink'] ?? null) ? $v['sink'] : 'spool',
            'label_controller' => \is_bool($v['label_controller'] ?? null) ? $v['label_controller'] : false,
            'profile_console' => \is_bool($v['profile_console'] ?? null) ? $v['profile_console'] : false,
            'base_labels' => $baseLabels,
            'pyroscope' => [
                'server_url' => self::nullableString($pyroscope['server_url'] ?? null),
                'basic_auth_user' => self::nullableString($pyroscope['basic_auth_user'] ?? null),
                'basic_auth_password' => self::nullableString($pyroscope['basic_auth_password'] ?? null),
                'tenant' => self::nullableString($pyroscope['tenant'] ?? null),
            ],
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private static function isBlank(?string $value): bool
    {
        return null === $value || '' === $value;
    }

    private static function isPlaceholder(string $value): bool
    {
        return str_contains($value, '%');
    }
}
