<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tracewick\Bridge\Symfony\DependencyInjection\Configuration;
use Tracewick\Runtime\ProfilerConfig;

/**
 * @phpstan-import-type TracewickConfig from Configuration
 */
#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function testDefaultsWhenDisabled(): void
    {
        $config = $this->process([['enabled' => false]]);

        self::assertFalse($config['enabled']);
        self::assertNull($config['application_name']);
        self::assertSame(ProfilerConfig::DEFAULT_SAMPLE_RATE, $config['sample_rate']);
        self::assertNull($config['spool_directory']);
        self::assertSame('spool', $config['sink']);
        self::assertSame([], $config['base_labels']);
        self::assertNull($config['pyroscope']['server_url']);
    }

    public function testValidSpoolConfig(): void
    {
        $config = $this->process([[
            'application_name' => 'blog',
            'spool_directory' => '/var/spool/tracewick',
            'base_labels' => ['env' => 'prod', 'runtime' => 'fpm'],
        ]]);

        self::assertTrue($config['enabled']);
        self::assertSame('blog', $config['application_name']);
        self::assertSame('/var/spool/tracewick', $config['spool_directory']);
        self::assertSame(['env' => 'prod', 'runtime' => 'fpm'], $config['base_labels']);
    }

    public function testValidPyroscopeConfig(): void
    {
        $config = $this->process([[
            'application_name' => 'blog',
            'sink' => 'pyroscope',
            'pyroscope' => [
                'server_url' => 'http://pyroscope:4040',
                'basic_auth_user' => 'user',
                'basic_auth_password' => 'secret',
                'tenant' => 'acme',
            ],
        ]]);

        self::assertSame('pyroscope', $config['sink']);
        self::assertSame('http://pyroscope:4040', $config['pyroscope']['server_url']);
        self::assertSame('acme', $config['pyroscope']['tenant']);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('provideInvalidConfigs')]
    public function testInvalidConfig(array $input, string $messageFragment): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($messageFragment);

        $this->process([$input]);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function provideInvalidConfigs(): iterable
    {
        yield 'enabled without application_name' => [
            ['enabled' => true],
            'application_name is required',
        ];

        yield 'enabled spool without directory' => [
            [
                'enabled' => true,
                'application_name' => 'blog',
                'sink' => 'spool',
            ],
            'spool_directory is required',
        ];

        yield 'pyroscope without server_url' => [
            [
                'enabled' => true,
                'application_name' => 'blog',
                'sink' => 'pyroscope',
            ],
            'server_url is required',
        ];

        yield 'sample_rate too high' => [
            [
                'enabled' => false,
                'sample_rate' => 1.5,
            ],
            'sample_rate',
        ];

        yield 'basic auth user without password' => [
            [
                'enabled' => false,
                'pyroscope' => [
                    'basic_auth_user' => 'user',
                ],
            ],
            'basic authentication needs both',
        ];

        yield 'unknown sink' => [
            [
                'enabled' => false,
                'sink' => 'otlp',
            ],
            'sink must be "spool" or "pyroscope"',
        ];
    }

    public function testEnvPlaceholdersSkipConcreteSinkChecks(): void
    {
        $config = $this->process([[
            'application_name' => '%env(TRACEWICK_APPLICATION)%',
            'spool_directory' => '%env(TRACEWICK_SPOOL_DIR)%',
            'sink' => '%env(TRACEWICK_SINK)%',
        ]]);

        self::assertSame('%env(TRACEWICK_SINK)%', $config['sink']);
    }

    public function testDisabledSkipsPyroscopeServerUrlRequirement(): void
    {
        $config = $this->process([[
            'enabled' => false,
            'sink' => 'pyroscope',
        ]]);

        self::assertFalse($config['enabled']);
        self::assertSame('pyroscope', $config['sink']);
        self::assertNull($config['pyroscope']['server_url']);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return TracewickConfig
     */
    private function process(array $configs): array
    {
        /** @var TracewickConfig $config */
        $config = (new Processor())->processConfiguration(new Configuration(), $configs);

        return $config;
    }
}
