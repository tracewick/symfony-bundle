<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tracewick\Bridge\Symfony\DependencyInjection\Configuration;
use Tracewick\Bridge\Symfony\DependencyInjection\TracewickExtension;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickRequestSubscriber;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * @phpstan-import-type TracewickConfig from Configuration
 */
#[CoversClass(TracewickExtension::class)]
#[CoversClass(Configuration::class)]
final class TracewickExtensionTest extends TestCase
{
    public function testLoadStoresConfigParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $extension->load([[
            'application_name' => 'blog',
            'spool_directory' => '/var/spool/tracewick',
            'sample_rate' => 0.25,
            'base_labels' => ['env' => 'test'],
        ]], $container);

        self::assertTrue($container->getParameter('tracewick.enabled'));
        /** @var TracewickConfig $config */
        $config = $container->getParameter('tracewick.config');
        self::assertSame('blog', $config['application_name']);
        self::assertSame(0.25, $config['sample_rate']);
        self::assertSame('/var/spool/tracewick', $config['spool_directory']);
        self::assertSame(['env' => 'test'], $config['base_labels']);
        self::assertTrue($container->hasDefinition('tracewick.profiler'));
        self::assertTrue($container->hasAlias(HttpProfiler::class));
        self::assertTrue($container->hasDefinition(TracewickRequestSubscriber::class));
        self::assertTrue(
            $container->getDefinition(TracewickRequestSubscriber::class)->hasTag('kernel.event_subscriber'),
        );
    }

    public function testLoadAcceptsDisabledWithoutRequiredFields(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $extension->load([['enabled' => false]], $container);

        self::assertFalse($container->getParameter('tracewick.enabled'));
        self::assertFalse($container->hasDefinition('tracewick.profiler'));
        self::assertFalse($container->hasDefinition(TracewickRequestSubscriber::class));
    }

    public function testLoadSkipsApplicationNameAssertWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $extension->load([[
            'enabled' => false,
            'application_name' => 'bad name!',
        ]], $container);

        self::assertFalse($container->getParameter('tracewick.enabled'));
        /** @var array{application_name: ?string} $config */
        $config = $container->getParameter('tracewick.config');
        self::assertSame('bad name!', $config['application_name']);
    }

    public function testLoadAcceptsEnvPlaceholdersForApplicationName(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $extension->load([[
            'application_name' => '%env(TRACEWICK_APPLICATION)%',
            'spool_directory' => '%env(TRACEWICK_SPOOL_DIR)%',
        ]], $container);

        /** @var TracewickConfig $config */
        $config = $container->getParameter('tracewick.config');
        self::assertSame('%env(TRACEWICK_APPLICATION)%', $config['application_name']);
    }

    public function testLoadRejectsInvalidApplicationName(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid tracewick.application_name');

        $extension->load([[
            'application_name' => 'bad name!',
            'spool_directory' => '/var/spool/tracewick',
        ]], $container);
    }

    public function testEmptyConfigFailsWhenEnabledByDefault(): void
    {
        $container = new ContainerBuilder();
        $extension = new TracewickExtension();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('application_name is required');

        $extension->load([], $container);
    }
}
