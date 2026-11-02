<?php

namespace Metribay\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Metribay\Core\Models\Project;
use Metribay\Core\Services\ProjectFavicon;

final class ProjectFaviconController
{
    public const string CONTENT_SECURITY_POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

    public function __invoke(Request $request, ProjectFavicon $favicons): Response
    {
        $project = Project::query()->findOrFail($request->route('project'));
        $icon = $project->domain ? $favicons->forDomain($project->domain) : null;
        $fallback = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#8a8f98" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/></svg>';

        return response($icon['body'] ?? $fallback, 200, [
            'Content-Type' => $icon['type'] ?? 'image/svg+xml',
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
