<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Functional\App;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;
use Tracewick\Bridge\Symfony\Tests\Support\RecordingHttpProfiler;
use Tracewick\Bridge\Symfony\TracewickBundle;

/**
 * Minimal FrameworkBundle + TracewickBundle app for functional HTTP wiring tests.
 */
final class FunctionalKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(
        string $environment,
        bool $debug,
        private readonly string $spoolDirectory,
        private readonly bool $tracewickEnabled = true,
        private readonly bool $recordProfiler = false,
    ) {
        parent::__construct($environment, $debug);
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    public function getCacheDir(): string
    {
        return $this->spoolDirectory . '/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->spoolDirectory . '/log';
    }

    /**
     * @return iterable<int, FrameworkBundle|TracewickBundle>
     */
    public function registerBundles(): iterable
    {
        // Symfony 8.1 deprecates HttpKernel BundleInterface in the Kernel return PHPDoc;
        // FrameworkBundle/TracewickBundle still implement the HttpKernel interface on SF 6.4–8.0.
        yield new FrameworkBundle();
        yield new TracewickBundle();
    }

    public function home(): Response
    {
        return new Response('ok', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    protected function build(ContainerBuilder $container): void
    {
        if (!$this->recordProfiler) {
            return;
        }

        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasDefinition('tracewick.profiler') && !$container->hasAlias('tracewick.profiler')) {
                    return;
                }

                $definition = (new Definition(RecordingHttpProfiler::class))->setPublic(true);
                $container->setDefinition('tracewick.profiler', $definition);
                $container->setAlias(HttpProfiler::class, 'tracewick.profiler')->setPublic(true);
            }
        });
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'php_errors' => ['log' => false],
            'router' => ['utf8' => true],
        ]);

        $container->extension('tracewick', [
            'enabled' => $this->tracewickEnabled,
            'application_name' => 'functional-test',
            'sample_rate' => 1.0,
            'spool_directory' => $this->spoolDirectory . '/spool',
            'sink' => 'spool',
            'base_labels' => [
                'env' => 'test',
            ],
        ]);

        // Swallow Tracewick diagnostics (e.g. sample_rate=1.0 notice) under PHPUnit output strictness.
        $container->services()
            ->set('logger', NullLogger::class);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('home', '/')->controller('kernel::home');
    }
}
