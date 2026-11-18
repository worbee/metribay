<?php

namespace Metribay\Core\Actions\Passkeys;

use Laravel\Passkeys\Actions\StorePasskey as BaseStorePasskey;
use Laravel\Passkeys\Exceptions\InvalidPasskeyException;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\CredentialRecord;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredentialCreationOptions;

/** A failed WebAuthn registration check is a refused passkey, not a server error. */
class StorePasskey extends BaseStorePasskey
{
    protected function validate(AuthenticatorAttestationResponse $response, PublicKeyCredentialCreationOptions $options): CredentialRecord
    {
        try {
            return parent::validate($response, $options);
        } catch (WebauthnException) {
            throw InvalidPasskeyException::make('Unable to register passkey. Please try again.');
        }
    }
}
