<?php

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Metribay\Core\Connectors\ConnectorRegistry;
use Metribay\Core\Connectors\SourceConfiguration;
use Metribay\Core\Contracts\ConnectionCredentials;
use Metribay\Core\Contracts\PageSpeedCredentials;
use Metribay\Core\Models\Connection;
use Metribay\Core\Models\SearchQueryMonth;
use Metribay\Core\Models\Source;
use Metribay\Core\Models\SyncRun;

class Collector
{
    public function __construct(private ConnectorRegistry $connectors, private MetricStore $store) {}

    public function run(Source $source, bool $full = false): SyncRun
    {
        $source = Source::query()->findOrFail($source->id);
        $now = CarbonImmutable::now('UTC');
        $version = $source->version;
        $connection = $source->connection;
        $connectionVersion = $connection?->version;
        $run = SyncRun::create(['source_id' => $source->id, 'started_at' => $now, 'status' => 'running']);
        $source->forceFill(['last_started_at' => $now])->save();
        try {
            $credentials = $connection ? app(ConnectionCredentials::class)->resolve($connection) : ($source->kind === 'pagespeed' ? app(PageSpeedCredentials::class)->resolve() : []);
            $from = null;
            // Recheck recent revisions while keeping exact current/previous visitor period queries.
            // Git history is always recomputed across the configured window (force pushes/late merges).
            if (! $full && $source->last_success_at && ! in_array($source->kind, ['github', 'github_releases', 'feed', 'sitemap', 'azure_devops', 'uptimerobot', 'pagespeed'], true)) {
                $today = $now->setTimezone($source->reporting_timezone)->startOfDay();
                if ($source->kind === 'paddle') {
                    $today = $today->subDay();
                }
                $days = $source->config[$source->kind === 'wporg' ? 'limit' : 'days'] ?? 180;
                $overlap = (int) config('metribay.collection.revision_overlap_days', 7);
                $from = $source->last_success_at->setTimezone($source->reporting_timezone)->startOfDay()->subDays($overlap)->max($today->subDays($days - 1));
            }
            $archived = in_array($source->kind, ProviderCatalog::QUERY_PROVIDERS, true)
                ? SearchQueryMonth::query()->where('source_id', $source->id)->pluck('month')->map(fn (CarbonImmutable $month): string => $month->format('Y-m'))->all() : [];
            $result = ProviderCatalog::withoutConvertedEvents($source->kind, $source->config ?? [], $this->connectors->for($source->kind)->collect(new SourceConfiguration($source->kind, $source->config ?? [], $source->reporting_timezone, $from, $archived), $credentials, $now));
            $finished = CarbonImmutable::now('UTC');
            DB::transaction(function () use ($source, $result, $now, $finished, $version, $connection, $connectionVersion, $run): void {
                $fresh = Source::query()->lockForUpdate()->findOrFail($source->id);
                $changedConnection = $connection && Connection::query()->whereKey($connection->id)->lockForUpdate()->value('version') !== $connectionVersion;
                if ($fresh->version !== $version || ! $fresh->enabled || $fresh->project->archived || $changedConnection) {
                    $run->update(['status' => 'cancelled', 'finished_at' => $finished]);

                    return;
                }
                $rows = $this->store->persist($fresh, $result, $now);
                $fresh->forceFill(['last_success_at' => $finished, 'consecutive_failures' => 0, 'error_code' => null])->save();
                $run->update(['status' => 'success', 'rows_written' => $rows, 'finished_at' => $finished]);
            });
        } catch (\Throwable $error) {
            $code = class_basename($error);
            DB::transaction(function () use ($source, $run, $code): void {
                $finished = CarbonImmutable::now('UTC');
                Source::query()->whereKey($source->id)->update(['last_error_at' => $finished, 'consecutive_failures' => DB::raw('consecutive_failures + 1'), 'error_code' => $code]);
                $run->update(['status' => 'failed', 'error_code' => $code, 'finished_at' => $finished]);
            });
        }

        return $run->fresh();
    }
}
