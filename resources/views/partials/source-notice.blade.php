@if($source['status'] === 'failed')
@php($errorCode = $source['error_code'] ?? '')
@php($googleExpired = $errorCode === 'GoogleAuthorizationExpired')
<div id="source-attention-{{ $source['id'] }}" class="notice notice-warning group-notice">@include('metribay::partials/icon',['name'=>'alert'])<div><strong>{{ $providerName }}</strong> · {{ match(true) {
    $errorCode === 'ProviderRequestLimit' => __('The Cloud provider request limit was reached. Scheduled imports will retry automatically.'),
    $errorCode === 'GitHubDeploymentAccess' => __('GitHub did not allow reading deployments. Allow read access to deployments for this connected account, or clear the deployment environments.'),
    $errorCode === 'AzureDevOpsDeploymentAccess' => __('Azure DevOps did not allow reading environments. Give the access token the Environment (Read & manage) scope, or clear the deployment environments.'),
    $googleExpired => __('Google access for this data source’s connected account expired or was revoked. Reconnect the account to resume collection.'),
    $errorCode !== '' => __('Collection needs attention. Reference: :code. Check the data source settings and its connected account.', ['code' => $errorCode]),
    default => __('This data source needs attention. Review its latest status and collection settings.'),
} }} {{ __('Existing history is shown below.') }} @if($googleExpired && ($canManageConnections ?? false) && !empty($source['connection_id']))<a class="text-link" href="{{ route('metribay.settings',['workspace'=>$workspace->slug,'tab'=>'accounts']) }}#connection-{{ $source['connection_id'] }}">{{ __('Reconnect account') }} →</a>@elseif($canEdit ?? false)<a class="text-link" href="{{ route('metribay.projects.edit',['workspace'=>$workspace->slug,'project'=>$project->id]) }}#source-{{ $source['id'] }}">{{ __('Review data source') }} →</a>@endif</div></div>
@elseif($source['status'] === 'disabled' && empty($source['connection_id']) && \Metribay\Core\Services\ProviderCatalog::requiresConnection($source['kind']))
<div id="source-attention-{{ $source['id'] }}" class="notice group-notice">@include('metribay::partials/icon',['name'=>'key'])<div><strong>{{ $providerName }}</strong> · {{ __('Paused: this data source has no connected account. Choose an account to resume collection.') }} {{ __('Existing history is shown below.') }} @if($canEdit ?? false)<a class="text-link" href="{{ route('metribay.projects.edit',['workspace'=>$workspace->slug,'project'=>$project->id]) }}#source-{{ $source['id'] }}">{{ __('Choose an account') }} →</a>@endif</div></div>
@elseif($source['status'] === 'delayed')
<div id="source-attention-{{ $source['id'] }}" class="notice group-notice">@include('metribay::partials/icon',['name'=>'clock'])<div><strong>{{ $providerName }}</strong> · {{ __('Recent data is delayed. Providers may take time to finalize reporting dates.') }}</div></div>
@elseif($source['status'] === 'pending')
<div class="notice group-notice" role="status" data-import-pending>@include('metribay::partials/icon',['name'=>'clock'])<div><strong>{{ $providerName }}</strong> · {{ __('Your first import is in progress. Metrics will appear here when it finishes.') }}</div></div>
@endif
