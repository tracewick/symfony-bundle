<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony\Tests\Support;

use Tracewick\Profile\Profile;
use Tracewick\Sink\Sink;
use Tracewick\Transport\Delivery;

/**
 * Sink that records profiles for adapter / factory mutation tests.
 */
final class RecordingSink implements Sink
{
    /** @var list<Profile> */
    public array $sent = [];

    public function send(Profile $profile): Delivery
    {
        $this->sent[] = $profile;

        return Delivery::delivered();
    }
}
