<?php

namespace Metribay\Core\Http\Responses;

use Laravel\Passkeys\Http\Responses\PasskeyRegistrationResponse as BaseResponse;

/** Profile adds passkeys with JavaScript and then reloads, so keep a status for that next page. */
class PasskeyRegistrationResponse extends BaseResponse
{
    public function toResponse($request)
    {
        if ($request->wantsJson() && $request->hasSession()) {
            $request->session()->flash('status', 'passkey-registered');
        }

        return parent::toResponse($request);
    }
}
