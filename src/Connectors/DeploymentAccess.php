<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** The provider refused deployment data for the chosen environments. The message is safe to display. */
abstract class DeploymentAccess extends ConnectorException {}
