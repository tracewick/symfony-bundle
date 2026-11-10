<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;

/**
 * Minimal test polyfill when symfony/messenger is not installed locally.
 */
interface MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope;
}
