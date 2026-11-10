<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Tracewick\Bridge\Symfony\DependencyInjection\Configuration;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickRequestSubscriber;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;
use Tracewick\Bridge\Symfony\Profiler\ProfilerFactory;
use Tracewick\Bridge\Symfony\Profiler\UnitOfWorkHttpProfiler;
use Tracewick\Bridge\Symfony\Tests\Functional\App\FunctionalKernel;
use Tracewick\Bridge\Symfony\Tests\Support\RecordingHttpProfiler;
use Tracewick\Bridge\Symfony\TracewickBundle;

/**
 * Boots a minimal FrameworkBundle kernel and exercises Tracewick HTTP + spool wiring.
 *
 * @phpstan-import-type TracewickConfig from Configuration
 */
#[CoversClass(TracewickBundle::class)]
#[CoversClass(TracewickRequestSubscriber::class)]
#[CoversClass(ProfilerFactory::class)]
#[CoversClass(UnitOfWorkHttpProfiler::class)]
final class HttpSpoolFunctionalTest extends TestCase
{
    private string $workdir;

    private ?string $previousErrorLog = null;

    protected function setUp(): void
    {
        $this->workdir = sys_get_temp_dir() . '/tracewick-sb-func-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->workdir . '/spool', 0o777, true));

        // Core logs sample_rate via error_log(); keep PHPUnit output strictness quiet.
        $previous = ini_set('error_log', $this->workdir . '/php-error.log');
        $this->previousErrorLog = false === $previous ? null : $previous;
    }

    protected function tearDown(): void
    {
        if (null !== $this->previousErrorLog) {
            ini_set('error_log', $this->previousErrorLog);
        }

        $this->removeTree($this->workdir);
    }

    public function testEnabledRequestLifecycleUsesSubscriberAndSpoolConfig(): void
    {
        $kernel = $this->bootKernel(enabled: true, recordProfiler: true);
        $container = $this->testContainer($kernel);

        self::assertTrue($container->has(TracewickRequestSubscriber::class));
        self::assertTrue($container->has('tracewick.profiler'));

        $profiler = $container->get('tracewick.profiler');
        self::assertInstanceOf(RecordingHttpProfiler::class, $profiler);

        $request = Request::create('/');
        $response = $kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getContent());

        $kernel->terminate($request, $response);

        self::assertSame([
            ['start', ['route' => 'unmatched', 'method' => 'GET']],
            ['label', 'route', 'home'],
            ['stop'],
            ['flush'],
        ], $profiler->calls);

        /** @var TracewickConfig $config */
        $config = $container->getParameter('tracewick.config');
        self::assertSame('spool', $config['sink']);
        self::assertSame($this->workdir . '/spool', $config['spool_directory']);
        self::assertSame(1.0, $config['sample_rate']);

        $kernel->shutdown();
    }

    public function testDisabledSkipsProfilerServicesAndLeavesSpoolEmpty(): void
    {
        $kernel = $this->bootKernel(enabled: false, recordProfiler: false);
        $container = $this->testContainer($kernel);

        self::assertFalse($container->has(TracewickRequestSubscriber::class));
        self::assertFalse($container->has('tracewick.profiler'));
        self::assertFalse($container->has(HttpProfiler::class));

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        foreach ($dispatcher->getListeners(KernelEvents::REQUEST) as $listener) {
            if (!\is_array($listener)) {
                continue;
            }

            self::assertNotInstanceOf(TracewickRequestSubscriber::class, $listener[0]);
        }

        $request = Request::create('/');
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        self::assertSame([], $this->spoolEntries());

        $kernel->shutdown();
    }

    public function testEnabledRealProfilerBootsWithSpoolSinkWithoutExcimer(): void
    {
        $kernel = $this->bootKernel(enabled: true, recordProfiler: false);
        $container = $this->testContainer($kernel);

        $profiler = $container->get(HttpProfiler::class);
        self::assertInstanceOf(UnitOfWorkHttpProfiler::class, $profiler);

        $request = Request::create('/');
        $response = $kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        $kernel->terminate($request, $response);

        // Without ext-excimer, empty profiles are dropped — spool stays empty (fail-open).
        if (!\extension_loaded('excimer')) {
            self::assertSame([], $this->spoolEntries());
        }

        $kernel->shutdown();
    }

    #[RequiresPhpExtension('excimer')]
    public function testEnabledRealProfilerWritesSpoolWhenExcimerIsLoaded(): void
    {
        $kernel = $this->bootKernel(enabled: true, recordProfiler: false);

        $request = Request::create('/');
        $response = $kernel->handle($request);

        // Give the sampler a little wall time so a non-empty stack can land.
        $until = microtime(true) + 0.05;
        while (microtime(true) < $until) {
            hash('xxh128', (string) microtime(true));
        }

        $kernel->terminate($request, $response);
        $kernel->shutdown();

        self::assertNotSame([], $this->spoolEntries(), 'Expected at least one spool file when ext-excimer is loaded.');
    }

    private function bootKernel(bool $enabled, bool $recordProfiler): FunctionalKernel
    {
        $environment = \sprintf(
            'func_%s_%s',
            $enabled ? 'on' : 'off',
            $recordProfiler ? 'rec' : 'real',
        );

        $kernel = new FunctionalKernel(
            $environment,
            true,
            $this->workdir,
            $enabled,
            $recordProfiler,
        );
        $kernel->boot();

        return $kernel;
    }

    private function testContainer(FunctionalKernel $kernel): ContainerInterface
    {
        $container = $kernel->getContainer();
        if ($container->has('test.service_container')) {
            $testContainer = $container->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $testContainer);

            return $testContainer;
        }

        return $container;
    }

    /**
     * @return list<string>
     */
    private function spoolEntries(): array
    {
        $entries = glob($this->workdir . '/spool/*');

        return false === $entries ? [] : $entries;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $pathname = $file->getPathname();
            if ($file->isDir()) {
                rmdir($pathname);
            } else {
                unlink($pathname);
            }
        }

        rmdir($path);
    }
}
