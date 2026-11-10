<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Stamp;

/**
 * Minimal test polyfill when symfony/messenger is not installed locally.
 */
final class ReceivedStamp implements StampInterface
{
    public function __construct(
        private string $transportName,
    ) {}

    public function getTransportName(): string
    {
        return $this->transportName;
    }
}
