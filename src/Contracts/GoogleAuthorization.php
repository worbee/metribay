<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

/** The host owns OAuth app registration, configuration and access policy. */
interface GoogleAuthorization
{
    public function available(): bool;

    public function label(): string;

    /** @return array{client_id: string, client_secret: string, redirect_uri: string} */
    public function client(): array;

    /** @return array<string, mixed> Encrypted connection payload; managed hosts store references, not app secrets. */
    public function credentials(string $refreshToken): array;
}
