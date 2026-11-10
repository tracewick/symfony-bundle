<?php

declare(strict_types=1);

/**
 * Loads Symfony Messenger API polyfills for unit tests when the real package is absent.
 * CI installs symfony/messenger via require-dev; this file is a no-op then.
 */
if (!interface_exists(\Symfony\Component\Messenger\Middleware\MiddlewareInterface::class, false)) {
    require __DIR__ . '/MessengerPolyfill/StampInterface.php';
    require __DIR__ . '/MessengerPolyfill/ReceivedStamp.php';
    require __DIR__ . '/MessengerPolyfill/Envelope.php';
    require __DIR__ . '/MessengerPolyfill/MiddlewareInterface.php';
    require __DIR__ . '/MessengerPolyfill/StackInterface.php';
}
