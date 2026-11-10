<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;

/**
 * Minimal test polyfill when symfony/messenger is not installed locally.
 */
interface StackInterface
{
    public function next(): MiddlewareInterface;
}
