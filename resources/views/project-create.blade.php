@extends('metribay::layouts/app')
@section('title', __('Add project'))
@section('content')
<a class="breadcrumb" href="{{ route('metribay.dashboard', $workspace->slug) }}">@include('metribay::partials/icon',['name'=>'arrow-left']) {{ __('All projects') }}</a>
<header class="page-heading"><div>@if(\Illuminate\Support\Facades\Route::has('metribay.workspaces'))<div class="eyebrow">{{ $workspace->name }}</div>@endif<h1>{{ __('Add project') }}<span class="heading-dot">.</span></h1><p class="page-description">{{ __('A project is one website, app or product. Next, you add its data sources.') }}</p></div></header>
<ol class="setup-progress" aria-label="{{ __('Setup progress') }}"><li aria-current="step">{{ __('Project') }}</li><li>{{ __('Data source') }}</li><li>{{ __('First import') }}</li></ol>
<form class="form-stack form-width setup-body" method="POST" action="{{ route('metribay.projects.store', $workspace->slug) }}">@csrf
    <div class="form-grid"><div class="field"><label for="new-project-name">{{ __('Project name') }}</label><input id="new-project-name" name="name" value="{{ old('name') }}" maxlength="120" placeholder="{{ __('My project') }}" required autofocus></div><div class="field"><label for="new-project-domain">{{ __('Domain') }} <span class="optional">{{ __('optional') }}</span></label><input id="new-project-domain" name="domain" value="{{ old('domain') }}" placeholder="example.com" maxlength="253"></div></div>
    <div class="form-actions"><a class="button button-secondary" href="{{ route('metribay.dashboard', $workspace->slug) }}">{{ __('Cancel') }}</a><button class="button button-primary" type="submit">{{ __('Create project') }} →</button></div>
</form>
@endsection
