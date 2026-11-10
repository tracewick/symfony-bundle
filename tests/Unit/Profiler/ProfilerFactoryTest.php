<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\Profiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
        $profiler = $factory([
            'enabled' => true,
            'application_name' => 'blog',
            'sample_rate' => 0.1,
            'spool_directory' => sys_get_temp_dir() . '/tracewick-bundle-test-spool',
            'sink' => 'spool',
            'base_labels' => ['env' => 'test'],
            'pyroscope' => [
                'server_url' => null,
                'basic_auth_user' => null,
                'basic_auth_password' => null,
                'tenant' => null,
            ],
        ], sys_get_temp_dir(), new NullLogger());

        self::assertInstanceOf(UnitOfWorkHttpProfiler::class, $profiler);

        $profiler->start(['route' => 'unmatched', 'method' => 'GET']);
        $profiler->label('route', 'app_home');
        $profiler->stop();
        $profiler->flush();
    }

    public function testCreateReturnsNullProfilerWhenConfigBroken(): void
    {
        $factory = new ProfilerFactory();
        $profiler = $factory([
            'enabled' => true,
            'application_name' => '',
            'sample_rate' => 0.1,
            'spool_directory' => null,
            'sink' => 'spool',
            'base_labels' => [],
            'pyroscope' => [
                'server_url' => null,
                'basic_auth_user' => null,
                'basic_auth_password' => null,
                'tenant' => null,
            ],
        ], sys_get_temp_dir(), new NullLogger());

        self::assertInstanceOf(NullHttpProfiler::class, $profiler);
        $profiler->start();
        $profiler->stop();
        $profiler->flush();
    }
}
