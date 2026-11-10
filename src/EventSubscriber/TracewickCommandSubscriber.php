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
 *
 * When Messenger profiling is also enabled, long-lived `messenger:consume` is skipped so the
 * command unit of work does not collide with per-message middleware profiles.
 */
final readonly class TracewickCommandSubscriber implements EventSubscriberInterface
{
    private const SKIP_WHEN_MESSENGER_PROFILING = [
        'messenger:consume',
        'messenger:consume-messages',
    ];

    public function __construct(
        private HttpProfiler $profiler,
        private bool $profileMessenger = false,
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
            if ($this->shouldSkip($event)) {
                return;
            }

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
            if ($this->shouldSkipTerminate($event)) {
                return;
            }

            $this->profiler->stop();
            $this->profiler->flush();
        } catch (\Throwable) {
            // Fail-open.
        }
    }

    private function shouldSkip(ConsoleCommandEvent $event): bool
    {
        if (!$this->profileMessenger) {
            return false;
        }

        return \in_array($this->commandName($event), self::SKIP_WHEN_MESSENGER_PROFILING, true);
    }

    private function shouldSkipTerminate(ConsoleTerminateEvent $event): bool
    {
        if (!$this->profileMessenger) {
            return false;
        }

        $command = $event->getCommand();
        if (!$command instanceof Command) {
            return false;
        }

        $name = $command->getName();

        return \is_string($name) && \in_array($name, self::SKIP_WHEN_MESSENGER_PROFILING, true);
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
