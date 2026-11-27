<?php

declare(strict_types=1);

namespace Metribay\Core\Contracts;

use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\ManagedSource;
use Metribay\Core\Services\ManagedSourceOffer;

/**
 * Data sources the host runs for a project without a Source record or a connected account,
 * such as managed SEO tracking. Core lists them with the project's other data sources.
 * Both methods run inside the workspace context and must not call providers.
 */
interface ManagedSources
{
    /** @return list<ManagedSource> Sources set up for the project, including paused ones. */
    public function forProject(Workspace $workspace, Project $project): array;

    /** @return list<ManagedSourceOffer> Choices for the provider picker that the current user may open. */
    public function offers(Workspace $workspace, Project $project): array;
}
