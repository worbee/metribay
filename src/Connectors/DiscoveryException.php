<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Safe, actionable discovery messages; never contains provider response bodies. */
class DiscoveryException extends \RuntimeException {}
