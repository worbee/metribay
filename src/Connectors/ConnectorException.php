<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Only bounded, credential-free messages may cross this boundary. */
final class ConnectorException extends \RuntimeException {}
