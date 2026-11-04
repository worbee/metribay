<?php

namespace Metribay\Core\Services;

use Illuminate\Http\Request;

final class UsageAnalytics
{
    /** An explicit setting wins; otherwise self-hosted installs don't ask. */
    public static function enabled(): bool
    {
        return (bool) (config('metribay.analytics.enabled') ?? config('metribay.edition') !== 'personal');
    }

    public static function page(Request $request): ?string
    {
        if (! self::enabled() || ! $request->isMethod('GET')) {
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
