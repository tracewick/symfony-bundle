<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Minimal test polyfill when symfony/messenger is not installed locally.
 */
final class Envelope
{
    /** @var array<class-string<StampInterface>, list<StampInterface>> */
    private array $stamps = [];

    public function __construct(
        private object $message,
        StampInterface ...$stamps,
    ) {
        foreach ($stamps as $stamp) {
            $this->stamps[$stamp::class][] = $stamp;
        }
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    public function with(StampInterface ...$stamps): self
    {
        $clone = clone $this;
        foreach ($stamps as $stamp) {
            $clone->stamps[$stamp::class][] = $stamp;
        }

        return $clone;
    }

    /**
     * @param class-string<StampInterface>|null $stampFqcn
     */
    public function last(?string $stampFqcn = null): ?StampInterface
    {
        if (null === $stampFqcn) {
            return null;
        }

        $stamps = $this->stamps[$stampFqcn] ?? [];

        return $stamps === [] ? null : $stamps[array_key_last($stamps)];
    }
}
