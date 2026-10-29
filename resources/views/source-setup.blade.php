@extends('metribay::layouts/app')
@section('title', 'Add a source')
@section('content')
@php($step = $draft['step'] ?? 'provider')
<header class="page-heading"><div>@if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<div class="eyebrow">{{ $workspace->name }}</div>@endif<h1>Add a source<span class="heading-dot">.</span></h1><p class="page-description">Connect your data and start your first import.</p></div><a class="text-link" href="{{ route('metribay.dashboard', $workspace->slug) }}">Save for later →</a></header>
<ol class="setup-progress" aria-label="Setup progress">@foreach(['provider'=>'Provider','account'=>'Account','resources'=>'Resource','confirm'=>'Connect'] as $key=>$title)<li @if($step === $key) aria-current="step" @endif>{{ $title }}</li>@endforeach</ol>
<div class="setup-body">
@if($step === 'provider')
<h2>Where is your data?</h2><p class="muted">Choose one provider to get started. You can add more later.</p>
<div class="provider-choices">@foreach($providers as $kind=>$provider)<form method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="provider"><input type="hidden" name="kind" value="{{ $kind }}"><button class="provider-choice" type="submit"><span>{{ $provider['label'] }}</span><span aria-hidden="true">→</span></button></form>@endforeach</div>
@else
<div class="section-heading"><h2>{{ $providers[$draft['kind']]['label'] }}</h2><form method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="restart"><button class="button button-quiet button-small">Change provider</button></form></div>
@if($step === 'account')
<p class="muted">Choose a saved account or connect another.</p>
@php($hasGoogleAuthorization = $canManageConnections && in_array($draft['kind'], ['ga4','gsc']) && $googleAuthorization->available())
@if($edition !== 'cloud')<p class="field-hint">Use credentials from your own provider account or developer app. Metribay Cloud offers managed connections and hosting. <a class="text-link" href="https://app.metribay.com/register" target="_blank" rel="noopener">Explore Cloud →</a></p>@endif
@if($hasGoogleAuthorization)<a class="button button-primary" href="{{ route('metribay.sources.google', $workspace->slug) }}">{{ $googleAuthorization->label() }} →</a>@endif
@if(config('metribay.source_setup.account_view')) @include(config('metribay.source_setup.account_view')) @endif
@if($connections->isNotEmpty())<form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="account"><div class="field"><label for="saved-account">Saved account</label><select id="saved-account" name="connection_id">@foreach($connections as $connection)<option value="{{ $connection->id }}">{{ $connection->name }}</option>@endforeach</select></div><div><button class="button button-primary">Continue →</button></div></form>@endif
@if($canManageConnections)
<details class="advanced-fields" @if($connections->isEmpty() && !$hasGoogleAuthorization && !($hasAdditionalAuthorization ?? false)) open @endif><summary>Use your own credentials</summary><form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="account">@include('metribay::partials/connection-fields', ['provider'=>$providers[$draft['kind']], 'kind'=>$draft['kind'], 'prefix'=>'setup'])<p class="field-hint">Credentials are encrypted. A saved account can be reused for other sources.</p><div><button class="button button-primary">Save account and continue →</button></div></form></details>
@else<p class="notice">Ask an owner or administrator to connect an account.</p>@endif
@elseif($step === 'resources')
@if(!empty($draft['discovery_error']))<p class="notice" role="alert">{{ $draft['discovery_error'] }}</p>@endif
@if(config('metribay.source_setup.resources_view')) @include(config('metribay.source_setup.resources_view')) @endif
<p class="muted">Select what you want to track. Only resources available to this account are listed.</p>
@if($canManageConnections || $draft['kind'] === 'wporg')<form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="discover">@if($draft['kind'] === 'wporg')<div class="field"><label for="plugin-search">Plugin name</label><input id="plugin-search" name="search" maxlength="120" placeholder="Search WordPress.org" value="{{ $draft['search'] ?? '' }}" required></div>@endif<div><button class="button button-primary">{{ isset($draft['listed']) ? 'Refresh resources' : ($draft['kind'] === 'wporg' ? 'Search plugins' : 'Find available resources') }}</button></div></form>@endif
@if(isset($draft['listed']))
@if(empty($draft['resources']))<p class="notice">No resources found. Check that this account has access, then refresh or use manual entry.</p>@else
<div class="field"><label for="resource-search">Filter resources</label><input id="resource-search" type="search" placeholder="Find a name or domain…" data-resource-search></div>
<div class="resource-choices">@foreach($draft['resources'] as $index=>$resource)<form data-resource-row method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="resource"><input type="hidden" name="resource" value="{{ $index }}"><button class="provider-choice"><span><strong>{{ $resource['name'] }}</strong><small>{{ $resource['domain'] ?: $resource['id'] }}</small></span><span aria-hidden="true">→</span></button></form>@endforeach</div>
@endif
@endif
<details class="advanced-fields"><summary>Enter a resource manually</summary><form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="manual"><div class="field"><label for="manual-resource">{{ ['umami'=>'Website UUID','ga4'=>'Property ID','gsc'=>'Property URL or sc-domain:example.com','bing'=>'Site URL','github'=>'owner/repository','uptimerobot'=>'Monitor ID','wporg'=>'Plugin slug'][$draft['kind']] }}</label><input id="manual-resource" name="resource_id" maxlength="500" required></div>@if($draft['kind'] === 'ga4')<div class="field"><label for="manual-timezone">Property reporting timezone</label><input id="manual-timezone" name="timezone" value="UTC" required><p class="field-hint">Match the timezone in Google Analytics.</p></div>@else<input type="hidden" name="timezone" value="{{ $providers[$draft['kind']]['default_timezone'] }}">@endif<div><button class="button button-secondary">Continue →</button></div></form></details>
@elseif($step === 'confirm')
<p>Connect <strong>{{ $draft['resource']['name'] }}</strong> to a project.</p>
<form class="form-stack" method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="finish">
<div class="field"><label for="setup-project">Project</label><select id="setup-project" name="project_id" data-setup-project><option value="">Create a new project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((array_key_exists('project_id', session()->getOldInput()) ? old('project_id') : ($draft['project_id'] ?? null)) == $project->id)>{{ $project->name }}</option>@endforeach</select></div>
<div data-new-project class="form-stack"><div class="field"><label for="setup-name">New project name</label><input id="setup-name" name="name" maxlength="120" value="{{ old('name', mb_substr($draft['resource']['name'], 0, 120)) }}"></div><div class="field"><label for="setup-domain">Domain <span class="optional">optional</span></label><input id="setup-domain" name="domain" maxlength="253" value="{{ old('domain', $draft['resource']['domain']) }}"></div></div>
<details class="advanced-fields"><summary>Advanced settings</summary><div class="form-stack"><div class="field"><label for="setup-label">Source label <span class="optional">optional</span></label><input id="setup-label" name="label" maxlength="120" value="{{ old('label') }}" placeholder="{{ $providers[$draft['kind']]['label'] }} · {{ $draft['resource']['name'] }}"></div><div class="field"><label for="setup-days">History window (days)</label><input id="setup-days" name="days" type="number" min="1" max="366" value="{{ old('days',180) }}" required></div><p class="field-hint">Reporting timezone: {{ $draft['resource']['timezone'] }}</p></div></details>
<p class="field-hint">This will connect the source and start importing data. New projects are created only when you confirm.</p><div><button class="button button-primary">Connect and start import →</button></div></form>
<form method="POST" action="{{ route('metribay.sources.setup', $workspace->slug) }}">@csrf<input type="hidden" name="action" value="back"><button class="button button-quiet">← Choose another resource</button></form>
@endif
@endif
</div>
@endsection
