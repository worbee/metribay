@php
    $advancedFields = in_array($kind,['ga4','gsc']) ? ['client_id','client_secret','refresh_token'] : ($kind === 'github' ? ['client_id','installation_id','private_key'] : []);
    $mainFields = array_values(array_diff($provider['fields'] ?? [], $advancedFields));
@endphp
<div class="form-stack">
@foreach($mainFields as $field)
    @include('metribay::partials/credential-field',['field'=>$field,'provider'=>$provider,'prefix'=>$prefix,'kind'=>$kind])
@endforeach
@if($advancedFields && count(array_intersect($advancedFields,$provider['fields'] ?? [])))<details class="advanced-fields"><summary>{{ $kind === 'github' ? __('Use a GitHub App instead') : __('Use OAuth credentials instead') }}</summary><p class="field-hint">{{ $kind === 'github' ? __('Provide the app’s client ID, installation ID, and PEM private key instead of a personal token.') : __('Provide a client ID, client secret, and refresh token instead of service-account JSON.') }}</p><div class="form-stack">@foreach(array_intersect($advancedFields,$provider['fields'] ?? []) as $field)@include('metribay::partials/credential-field',['field'=>$field,'provider'=>$provider,'prefix'=>$prefix])@endforeach</div></details>@endif
</div>
