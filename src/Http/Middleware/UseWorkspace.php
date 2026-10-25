<?php

namespace Metribay\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Metribay\Core\Contracts\WorkspaceAccess;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;

class UseWorkspace
{
    public function handle(Request $request, Closure $next): mixed
    {
        $workspace = $request->route('workspace');
        if (! $workspace instanceof Workspace) {
            $workspace = Workspace::query()->where('slug', $workspace)->firstOrFail();
            $request->route()->setParameter('workspace', $workspace);
        }
        abort_unless(app(WorkspaceAccess::class)->allows($request->user(), $workspace, 'view'), 404);
        $request->attributes->set('metribay.workspace', $workspace);

        return app(WorkspaceContext::class)->run($workspace, fn () => $next($request));
    }
}
