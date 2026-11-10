<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tracewick\Bridge\Symfony\Profiler\NullHttpProfiler;
use Tracewick\Bridge\Symfony\Profiler\ProfilerFactory;
use Tracewick\Bridge\Symfony\Profiler\UnitOfWorkHttpProfiler;

#[CoversClass(ProfilerFactory::class)]
#[CoversClass(UnitOfWorkHttpProfiler::class)]
#[CoversClass(NullHttpProfiler::class)]
final class ProfilerFactoryTest extends TestCase
{
    public function testCreateSpoolProfilerWithoutExcimer(): void
    {
        $factory = new ProfilerFactory();
        $profiler = $factory($this->config(), sys_get_temp_dir(), new NullLogger());

        self::assertInstanceOf(UnitOfWorkHttpProfiler::class, $profiler);

        $profiler->start(['route' => 'unmatched', 'method' => 'GET']);
        $profiler->label('route', 'app_home');
        $profiler->stop();
        $profiler->flush();
    }

    public function testCreateSpoolProfilerWithEmptyProjectDirAndDefaultLogger(): void
    {
        $factory = new ProfilerFactory();
        $profiler = $factory($this->config(), '');

        self::assertInstanceOf(UnitOfWorkHttpProfiler::class, $profiler);
    }

    public function testCreatePyroscopeProfilerWithoutExcimer(): void
    {
        $factory = new ProfilerFactory();
        $profiler = $factory($this->config([
            'sink' => 'pyroscope',
            'spool_directory' => null,
            'pyroscope' => [
                'server_url' => 'http://127.0.0.1:4040',
                'basic_auth_user' => 'user',
                'basic_auth_password' => 'secret',
                'tenant' => 'acme',
            ],
        ]), sys_get_temp_dir(), new NullLogger());

        self::assertInstanceOf(UnitOfWorkHttpProfiler::class, $profiler);

        $profiler->start(['route' => 'unmatched', 'method' => 'GET']);
        $profiler->label('route', 'app_home');
        $profiler->stop();
        $profiler->flush();
    }

    public function testCreateReturnsNullProfilerWhenApplicationNameEmpty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Tracewick profiler could not be created; profiling is disabled for this worker.',
                self::callback(static fn(array $context): bool => self::isFactoryGuardException(
                    $context,
                    'tracewick.application_name resolved to an empty value.',
                )),
            );

        $factory = new ProfilerFactory();
        $profiler = $factory($this->config([
            'application_name' => '',
        ]), sys_get_temp_dir(), $logger);

        self::assertInstanceOf(NullHttpProfiler::class, $profiler);
        $this->assertNullProfilerIsUsable($profiler);
    }

    public function testCreateReturnsNullProfilerWhenSpoolDirectoryEmpty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Tracewick profiler could not be created; profiling is disabled for this worker.',
                self::callback(static fn(array $context): bool => self::isFactoryGuardException(
                    $context,
                    'tracewick.spool_directory resolved to an empty value.',
                )),
            );

        $factory = new ProfilerFactory();
        $profiler = $factory($this->config([
            'spool_directory' => '',
        ]), sys_get_temp_dir(), $logger);

        self::assertInstanceOf(NullHttpProfiler::class, $profiler);
        $this->assertNullProfilerIsUsable($profiler);
    }

    public function testCreateReturnsNullProfilerWhenPyroscopeServerUrlEmpty(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Tracewick profiler could not be created; profiling is disabled for this worker.',
                self::callback(static fn(array $context): bool => self::isFactoryGuardException(
                    $context,
                    'tracewick.pyroscope.server_url resolved to an empty value.',
                )),
            );

        $factory = new ProfilerFactory();
        $profiler = $factory($this->config([
            'sink' => 'pyroscope',
            'spool_directory' => null,
            'pyroscope' => [
                'server_url' => '',
                'basic_auth_user' => null,
                'basic_auth_password' => null,
                'tenant' => null,
            ],
        ]), sys_get_temp_dir(), $logger);

        self::assertInstanceOf(NullHttpProfiler::class, $profiler);
        $this->assertNullProfilerIsUsable($profiler);
    }

    public function testCreateReturnsNullProfilerWhenPyroscopeServerUrlMissing(): void
    {
        $factory = new ProfilerFactory();
        $profiler = $factory($this->config([
            'sink' => 'pyroscope',
            'spool_directory' => null,
            'pyroscope' => [
                'server_url' => null,
                'basic_auth_user' => null,
                'basic_auth_password' => null,
                'tenant' => null,
            ],
        ]), sys_get_temp_dir(), new NullLogger());

        self::assertInstanceOf(NullHttpProfiler::class, $profiler);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array{
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
     * }
     */
    private function config(array $overrides = []): array
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
        $config = array_replace_recursive([
            'enabled' => true,
            'application_name' => 'blog',
            'sample_rate' => 0.1,
            'spool_directory' => sys_get_temp_dir() . '/tracewick-bundle-test-spool',
            'sink' => 'spool',
            'label_controller' => false,
            'profile_console' => false,
            'profile_messenger' => false,
            'base_labels' => ['env' => 'test'],
            'pyroscope' => [
                'server_url' => null,
                'basic_auth_user' => null,
                'basic_auth_password' => null,
                'tenant' => null,
            ],
        ], $overrides);

        return $config;
    }

    private function assertNullProfilerIsUsable(NullHttpProfiler $profiler): void
    {
        $profiler->start(['route' => 'unmatched']);
        $profiler->label('route', 'app_home');
        $profiler->stop();
        $profiler->flush();
    }

    /**
     * @param array<mixed> $context
     */
    private static function isFactoryGuardException(array $context, string $message): bool
    {
        $exception = $context['exception'] ?? null;

        return $exception instanceof \InvalidArgumentException && $message === $exception->getMessage();
    }
}
