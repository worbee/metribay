<?php

namespace Metribay\Core\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/** @property array<string, mixed>|null $encrypted_payload */
class Connection extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    protected $hidden = ['encrypted_payload'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'encrypted_payload' => 'encrypted:array'];
    }

    /** @param array<string, mixed> $values */
    public function storeSecrets(array $values): void
    {
        $this->encrypted_payload = ['workspace_id' => (int) $this->workspace_id, 'kind' => $this->kind, 'values' => $values];
    }

    /** Issued by the host's own provider app (for example Metribay Cloud's Google app): renewable only by signing in again. */
    public function hostManaged(): bool
    {
        try {
            return array_key_exists('managed_app', $this->reveal());
        } catch (DecryptException) {
            return false;
        }
    }

    /** A Google account authorized by signing in (a refresh token), whose access can be revoked at Google. */
    public function googleSignIn(): bool
    {
        if (! in_array($this->kind, ['ga4', 'gsc'], true)) {
            return false;
        }
        try {
            $token = $this->reveal()['refresh_token'] ?? null;
        } catch (DecryptException) {
            return false;
        }

        return is_string($token) && trim($token) !== '';
    }

    /** @return array<string, mixed> */
    public function reveal(): array
    {
        $payload = $this->encrypted_payload;
        if (! is_array($payload) || ($payload['workspace_id'] ?? null) !== (int) $this->workspace_id || ($payload['kind'] ?? null) !== $this->kind || ! is_array($payload['values'] ?? null)) {
            throw new DecryptException('Connection credentials are unavailable.');
        }

        return $payload['values'];
    }
}
