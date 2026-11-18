<?php

namespace Metribay\Core\Actions\Passkeys;

use Laravel\Passkeys\Actions\VerifyPasskey as BaseVerifyPasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Laravel\Passkeys\Passkey;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredentialRequestOptions;

/** A failed WebAuthn check (wrong challenge, origin or signature) is a refused passkey, not a server error. */
class VerifyPasskey extends BaseVerifyPasskey
{
    protected function validate(AuthenticatorAssertionResponse $response, Passkey $passkey, PublicKeyCredentialRequestOptions $options): CredentialRecord
    {
        try {
            return parent::validate($response, $passkey, $options);
        } catch (WebauthnException) {
            throw InvalidPasskeyException::make('Unable to verify passkey. Please try again.');
        }
    }
}
