<?php

declare(strict_types=1);

namespace Metribay\Core\Connectors;

/** Google rejected the saved authorization (invalid_grant): the refresh token expired or was revoked, or the key was disabled. Safe to display. */
final class GoogleAuthorizationExpired extends ConnectorException
{
    public function __construct()
    {
        parent::__construct('Google access for this connected account expired or was revoked. Reconnect the account.');
    }
}
