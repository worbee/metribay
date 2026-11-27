<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Metribay\Core\Contracts\ManagedSources;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;

/** Standalone Core runs no data sources on a user's behalf. */
final class NoManagedSources implements ManagedSources
{
    public function forProject(Workspace $workspace, Project $project): array
    {
        return [];
    }

    public function offers(Workspace $workspace, Project $project): array
    {
        return [];
    }
}
