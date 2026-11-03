<?php

namespace Metribay\Core\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\Collector;
use Metribay\Core\Services\WorkspaceContext;

class CollectSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 4;

    public int $timeout = 900;

    public int $uniqueFor = 21600;

    public function __construct(public int $workspaceId, public int $sourceId, public bool $full = false) {}

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function uniqueId(): string
    {
        return $this->workspaceId.':'.$this->sourceId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        $connection = DB::table('sources')->where('workspace_id', $this->workspaceId)->where('id', $this->sourceId)->value('connection_id');

        return [(new WithoutOverlapping('metribay-connection:'.$this->workspaceId.':'.($connection ?? 'public-'.$this->sourceId)))->shared()->releaseAfter(60)->expireAfter(960)];
    }

    public function handle(WorkspaceContext $context, Collector $collector): void
    {
        $workspace = Workspace::query()->find($this->workspaceId);
        if (! $workspace || ! config('metribay.collection.enabled', true)) {
            return;
        }
        $context->run($workspace, function () use ($collector): void {
            $source = Source::query()->find($this->sourceId);
            if (! $source || ! $source->enabled || $source->project->archived) {
                return;
            }
            $run = $collector->run($source, $this->full);
            if ($run->status === 'failed') {
                throw new \RuntimeException('Collection failed; inspect source health for the safe error category.');
            }
        });
    }
}
