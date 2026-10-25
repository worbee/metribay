@extends('metribay::layouts/app')
@section('title', 'Workspaces')
@section('content')
@php($workspaceCreationRoute = config('metribay.workspace_creation_route'))
<header class="page-heading"><div><div class="eyebrow">Choose a workspace</div><h1>Your workspaces</h1><p class="page-description">Projects, connections, and metric history stay within their workspace.</p></div>
@if (($canCreateWorkspace ?? false) && is_string($workspaceCreationRoute) && \Illuminate\Support\Facades\Route::has($workspaceCreationRoute))<a class="button button-primary" href="{{ route($workspaceCreationRoute) }}">@include('metribay::partials/icon', ['name'=>'plus']) New workspace</a>@endif
</header>
<div class="workspace-list">
@forelse ($workspaces as $item)
    <a class="workspace-row" href="{{ route('metribay.dashboard', $item->slug) }}"><span class="workspace-initial">{{ mb_strtoupper(mb_substr($item->name,0,1)) }}</span><div><h2>{{ $item->name }}</h2><span class="muted">{{ $item->slug }}</span></div>@include('metribay::partials/icon', ['name'=>'chevron'])</a>
@empty
    <div class="empty-state">@include('metribay::partials/icon', ['name'=>'layers'])<h2>No workspaces yet</h2><p>A workspace will appear here once your account has access.</p></div>
@endforelse
</div>
@if (($edition ?? config('metribay.edition')) !== 'cloud')<p class="footnote">Personal edition includes one owner and one workspace.</p>@endif
@endsection
