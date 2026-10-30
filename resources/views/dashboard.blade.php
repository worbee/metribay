@extends('metribay::layouts/app')
@section('title', __('Overview'))
@section('content')
@livewire('metribay-dashboard', ['workspaceId' => (int) $workspace->id, 'days' => (int) $days])
@endsection
