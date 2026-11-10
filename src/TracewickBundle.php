<?php

declare(strict_types=1);

namespace Tracewick\Bridge\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Official Symfony bridge for Tracewick.
 *
 * Uses Tracewick Runtime and Sink APIs only; the core library stays framework-agnostic.
 */
final class TracewickBundle extends Bundle {}
