<?php

namespace Metribay\Core\Services;

use Illuminate\Http\Request;

final class UsageAnalytics
{
    public static function page(Request $request): ?string
    {
        if (! config('metribay.analytics.enabled') || ! $request->isMethod('GET')) {
            return null;
        }

        return [
            'metribay.home' => 'home',
            'metribay.dashboard' => 'overview',
            'metribay.projects.show' => 'project',
            'metribay.projects.create' => 'new-project',
            'metribay.projects.edit' => 'project-settings',
            'metribay.settings' => 'settings',
            'metribay.profile' => 'profile',
            'metribay.events.index' => 'events',
            'metribay.sources.create' => 'add-source',
            'metribay.workspaces' => 'workspaces',
            'cloud.workspaces.create' => 'new-workspace',
            'login' => 'login',
            'register' => 'signup',
            'cloud.registration.pending' => 'check-email',
        ][$request->route()?->getName() ?? ''] ?? null;
    }
}
