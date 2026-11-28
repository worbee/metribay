<?php

declare(strict_types=1);

namespace Metribay\Core\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Metribay\Core\Models\DailyMetric;
use Metribay\Core\Models\MonthlyReport;
use Metribay\Core\Models\Project;
use Metribay\Core\Models\Workspace;
use Metribay\Core\Services\MonthlySnapshot;
use Metribay\Core\Services\WorkspaceContext;

/**
 * Runs daily. On the 1st through the 4th UTC the closed month is refreshed for late data. From the 5th it is sealed,
 * together with any older month that was left open, so a missed run is caught up the next day.
 * --month and --backfill write older closed months and seal them.
 */
class MonthlyReportsCommand extends Command
{
    /** The day of the month on which the closed month is sealed. */
    public const SEAL_DAY = 5;

    protected $signature = 'metribay:monthly-reports {--month=} {--seal} {--backfill}';

    protected $description = 'Save the monthly report snapshot for each active project';

    public function handle(MonthlySnapshot $snapshots, WorkspaceContext $context): int
    {
        $now = CarbonImmutable::now('UTC');
        $previous = $now->startOfMonth()->subMonth()->format('Y-m');
        $explicit = is_string($this->option('month')) && $this->option('month') !== '';
        if ($this->option('backfill')) {
            $months = $this->closedMonths($now);
        } elseif ($explicit) {
            if (! preg_match('/^\d{4}-\d{2}$/D', $this->option('month')) || $this->option('month') >= $now->format('Y-m')) {
                $this->error('Give a closed month as YYYY-MM.');

                return self::FAILURE;
            }
            $months = [$this->option('month')];
        } else {
            $months = [$previous];
        }
        $seal = (bool) $this->option('seal') || (bool) $this->option('backfill') || $explicit || (int) $now->day >= self::SEAL_DAY;
        $catchUp = $seal && $months === [$previous];
        $count = 0;
        Workspace::query()->orderBy('id')->each(function (Workspace $workspace) use ($context, $snapshots, $months, $seal, $catchUp, $previous, $now, &$count): void {
            $context->run($workspace, function () use ($workspace, $snapshots, $months, $seal, $catchUp, $previous, $now, &$count): void {
                Project::query()->where('archived', false)->orderBy('id')->each(function (Project $project) use ($workspace, $snapshots, $months, $seal, $catchUp, $previous, $now, &$count): void {
                    $open = $catchUp ? MonthlyReport::query()->where('project_id', $project->id)->whereNull('sealed_at')->whereDate('month', '<', $previous.'-01')
                        ->orderBy('month')->get()->map(fn (MonthlyReport $report): string => $report->month->format('Y-m'))->all() : [];
                    foreach ([...$open, ...$months] as $month) {
                        $snapshots->store($workspace, $project, $month, $seal, $now);
                        $count++;
                    }
                });
            });
        });
        $this->info('Monthly reports written: '.$count.($seal ? ' (sealed).' : ' (open for late data).'));

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function closedMonths(CarbonImmutable $now): array
    {
        $earliest = DailyMetric::query()->withoutGlobalScope('workspace')->min('date');
        if (! is_string($earliest)) {
            return [];
        }
        $cursor = CarbonImmutable::parse(substr($earliest, 0, 7).'-01')->startOfMonth();
        $last = $now->startOfMonth()->subMonth();
        $months = [];
        while ($cursor->lte($last)) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}
