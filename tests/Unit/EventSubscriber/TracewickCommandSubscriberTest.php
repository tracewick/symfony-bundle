<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tracewick\Bridge\Symfony\EventSubscriber\TracewickCommandSubscriber;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

#[CoversClass(TracewickCommandSubscriber::class)]
final class TracewickCommandSubscriberTest extends TestCase
{
    /** @var MockObject&HttpProfiler */
    private MockObject $profiler;

    private TracewickCommandSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->profiler = $this->createMock(HttpProfiler::class);
        $this->subscriber = new TracewickCommandSubscriber($this->profiler);
    }

    public function testSubscribedEventsMatchFrameworkPriorities(): void
    {
        self::assertSame([
            ConsoleEvents::COMMAND => ['startProfile', 4096],
            ConsoleEvents::TERMINATE => ['deliverProfile', -4096],
        ], TracewickCommandSubscriber::getSubscribedEvents());
    }

    public function testStartProfileLabelsCommandName(): void
    {
        $this->profiler->expects(self::once())
            ->method('start')
            ->with(['command' => 'app:import']);

        $this->subscriber->startProfile($this->commandEvent('app:import'));
    }

    public function testStartProfileUsesSymfonyConsoleWhenCommandMissing(): void
    {
        $this->profiler->expects(self::once())
            ->method('start')
            ->with(['command' => 'symfony_console']);

        $event = new ConsoleCommandEvent(null, new ArrayInput([]), new NullOutput());
        $this->subscriber->startProfile($event);
    }

    public function testStartProfileUsesSymfonyConsoleWhenCommandNameEmpty(): void
    {
        $this->profiler->expects(self::once())
            ->method('start')
            ->with(['command' => 'symfony_console']);

        $command = $this->createMock(Command::class);
        $command->method('getName')->willReturn('');

        $event = new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput());
        $this->subscriber->startProfile($event);
    }

    public function testDeliverProfileStopsAndFlushes(): void
    {
        $this->profiler->expects(self::once())->method('stop');
        $this->profiler->expects(self::once())->method('flush');

        $command = new Command('app:import');
        $event = new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), 0);

        $this->subscriber->deliverProfile($event);
    }

    public function testStartProfileSwallowsProfilerErrors(): void
    {
        $this->expectNotToPerformAssertions();
        $this->profiler->method('start')->willThrowException(new \RuntimeException('boom'));

        $this->subscriber->startProfile($this->commandEvent('app:import'));
    }

    public function testDeliverProfileSwallowsProfilerErrors(): void
    {
        $this->expectNotToPerformAssertions();
        $this->profiler->method('stop')->willThrowException(new \RuntimeException('boom'));

        $command = new Command('app:import');
        $event = new ConsoleTerminateEvent($command, new ArrayInput([]), new NullOutput(), 0);

        $this->subscriber->deliverProfile($event);
    }

    private function commandEvent(string $name): ConsoleCommandEvent
    {
        return new ConsoleCommandEvent(new Command($name), new ArrayInput([]), new NullOutput());
    }
}
