<?php

namespace Metribay\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Metribay\Core\Services\WorkspaceContext;

trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', /** @param Builder<static> $query */ function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('workspace_id'), app(WorkspaceContext::class)->id());
        });
        static::deleting(function ($model): void {
            if ((int) $model->workspace_id !== app(WorkspaceContext::class)->id()) {
                throw new LogicException('The record belongs to another workspace.');
            }
        });
        static::saving(function ($model): void {
            $workspaceId = app(WorkspaceContext::class)->id();
            if ($model->workspace_id !== null && (int) $model->workspace_id !== $workspaceId) {
                throw new LogicException('The record belongs to another workspace.');
            }
            $model->workspace_id = $workspaceId;
        });
    }
}
