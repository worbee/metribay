<?php

namespace Metribay\Core\Console;

use Illuminate\Console\Command;
use Metribay\Core\Jobs\CollectSource;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\WorkspaceContext;

class SyncCommand extends Command
{
    protected $signature = 'metribay:sync {--workspace=} {--kind=} {--except=} {--source=} {--inline} {--full}';

    protected $description = 'Dispatch workspace-scoped provider collection jobs';

    public function handle(WorkspaceContext $context): int
    {
        $count = 0;
        $failed = 0;
        foreach (Workspace::query()->when($this->option('workspace'), fn ($q) => $q->where('slug', $this->option('workspace')))->cursor() as $workspace) {
            $context->run($workspace, function () use ($workspace, &$count, &$failed): void {
                foreach (Source::query()->where('enabled', true)->whereHas('project', fn ($q) => $q->where('archived', false))->when($this->option('kind'), fn ($q) => $q->where('kind', $this->option('kind')))->when($this->option('except'), fn ($q) => $q->whereNot('kind', $this->option('except')))->when($this->option('source'), fn ($q) => $q->whereKey($this->option('source')))->orderBy('id')->get() as $source) {
                    if ($source->kind === 'pagespeed' && ! $this->option('source') && ! $this->option('full') && $source->last_success_at?->gt(now()->subDay())) {
                        continue;
                    }
                    $job = new CollectSource($workspace->id, $source->id, (bool) $this->option('full'));
                    if ($this->option('inline')) {
                        try {
                            dispatch_sync($job);
                        } catch (\Throwable) {
                            $failed++;
                        }
                    } else {
                        dispatch($job);
                    } $count++;
                }
            });
        }
        $this->info("Collection: {$count} source jobs, {$failed} inline failures.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
