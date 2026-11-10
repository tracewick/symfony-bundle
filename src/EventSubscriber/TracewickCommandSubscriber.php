<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\EventSubscriber;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tracewick\Bridge\Symfony\Profiler\HttpProfiler;

/**
 * Profiles Symfony console commands with Tracewick (core runtime.md CLI unit-of-work).
 *
 * Starts on ConsoleEvents::COMMAND, labels with the command name (never argv), and delivers on
 * TERMINATE. Fail-open: errors are swallowed at the listener boundary.
 */
final readonly class TracewickCommandSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private HttpProfiler $profiler,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Same priorities as FrameworkBundle's ConsoleProfilerListener.
            ConsoleEvents::COMMAND => ['startProfile', 4096],
            ConsoleEvents::TERMINATE => ['deliverProfile', -4096],
        ];
    }

    public function startProfile(ConsoleCommandEvent $event): void
    {
        try {
            $this->profiler->start([
                'command' => $this->commandName($event),
            ]);
        } catch (\Throwable) {
            // Fail-open: never break the command.
        }
    }

    public function deliverProfile(ConsoleTerminateEvent $event): void
    {
        try {
            $this->profiler->stop();
            $this->profiler->flush();
        } catch (\Throwable) {
            // Fail-open.
        }
    }

    private function commandName(ConsoleCommandEvent $event): string
    {
        $command = $event->getCommand();
        if (!$command instanceof Command) {
            return 'symfony_console';
        }

        $name = $command->getName();

        return (\is_string($name) && '' !== $name) ? $name : 'symfony_console';
    }
}
