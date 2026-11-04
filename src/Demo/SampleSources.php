<?php

declare(strict_types=1);

namespace Metribay\Core\Demo;

use Carbon\CarbonImmutable;
use Closure;
use Metribay\Core\Connectors\BingConnector;
use Metribay\Core\Connectors\CollectionResult;
use Metribay\Core\Connectors\GscConnector;

/**
 * Builds sample data sources the way their connectors report them: the same metric keys, partial and missing days,
 * provider timezones, unique-visitor periods, query windows and archived months, snapshots and provider events.
 * Expected levels come from the portfolio; this class adds counting noise and provider behaviour.
 */
final class SampleSources
{
    public function __construct(private readonly Noise $noise, private readonly CarbonImmutable $now, private readonly int $days) {}

    /**
     * GA4, Umami, Plausible or Matomo traffic. Visits come from $visits; visitors and page views follow from per-day ratios,
     * so a day always has visits >= visitors and page views >= visits. Unique visitors over 7, 30 and 90 days
     * are stored as periods and are never the sum of daily visitors. Matomo cannot return those period totals, so it sets periods to false.
     *
     * Options: pages: page views per visit; returning: visits per visitor on a day; loyalty: how strongly visitors return across days; periods: store period visitor totals.
     *
     * @param  array<string, mixed>  $config
     * @param  Closure(int, CarbonImmutable): float  $visits  Expected visits on a day.
     * @param  array{pages: float, returning: float, loyalty: float, first?: int, timezone?: string, gaps?: list<int>, bots?: array<int, int>, sigma?: float, periods?: bool}  $options
     */
    public function analytics(string $kind, string $label, array $config, Closure $visits, array $options): SampleSource
    {
        $timezone = $options['timezone'] ?? 'UTC';
        $today = $this->today($timezone);
        $first = $this->first($options['first'] ?? null);
        $noise = $this->noise->fork($kind.':'.$label);
        $level = $noise->fork('level')->wander($first + 1, $options['sigma'] ?? 0.09);
        $mix = $noise->fork('mix');
        $counts = $noise->fork('counts');
        $daily = [];
        $visitors = [];
        for ($ago = $first; $ago >= 0; $ago--) {
            $date = $today->subDays($ago);
            $perVisitor = max(1.03, $options['returning'] * (1 + 0.04 * $mix->normal()));
            $pages = min(3.0, max(1.8, $options['pages'] * (1 + 0.05 * $mix->normal())));
            if (in_array($ago, $options['gaps'] ?? [], true)) {
                continue; // The tracker was down: unknown, never zero.
            }
            // Today is still running everywhere; GA4 keeps processing the two days before it as well.
            $completeness = match (true) {
                $ago === 0 => $this->elapsed($timezone),
                $kind === 'ga4' && $ago === 1 => 0.97,
                $kind === 'ga4' && $ago === 2 => 0.995,
                default => 1.0,
            };
            $count = $counts->poisson($visits($ago, $date) * $level[$first - $ago] * $completeness);
            $people = max(min($count, 1), (int) round($count / $perVisitor));
            $views = (int) round($count * $pages);
            if ($bots = $options['bots'][$ago] ?? 0) {
                // Crawlers posing as browsers: one short visit each, so page views per visit fall that day.
                $count += $bots;
                $people += (int) round($bots * 0.97);
                $views += (int) round($bots * 1.05);
            }
            $partial = $kind === 'ga4' ? $ago < 3 : $ago === 0;
            $visitors[$ago] = $people;
            foreach (['visitors' => $people, 'visits' => $count, 'pageviews' => $views] as $metric => $value) {
                $daily[] = $this->row($metric, $date, $value, $partial);
            }
        }
        $periods = [];
        if ($options['periods'] ?? true) {
            foreach ([7, 30, 90] as $length) {
                foreach ([0, $length] as $offset) {
                    $window = array_filter($visitors, fn (int $ago): bool => $ago >= $offset && $ago < $offset + $length, ARRAY_FILTER_USE_KEY);
                    if ($window === []) {
                        continue; // No data in the window is unknown.
                    }
                    // People come back on several days, so a window has fewer unique visitors than the sum of its days.
                    $unique = max(max($window), (int) round(array_sum($window) / (1 + $options['loyalty'] * log($length))));
                    $periods[] = ['metric' => 'visitors', 'start_date' => $today->subDays($offset + $length - 1)->toDateString(), 'end_date' => $today->subDays($offset)->toDateString(),
                        'value' => (float) $unique, 'is_partial' => $kind === 'ga4' ? $offset < 3 : $offset === 0];
                }
            }
        }

        return $this->source($kind, $label, $config, $timezone, [new CollectionResult(daily: $daily, periods: $periods)], 9);
    }

    /**
     * Search Console or Bing. Daily totals are the sum of the top queries plus a long tail of rare queries, so clicks follow
     * from each query's position and the top queries never add up to more than the site.
     *
     * Options: coverage: share of impressions from the listed queries; rare: share of clicks from rare (anonymized) queries; shift: positions lost (positive) on a day.
     *
     * @param  array<string, mixed>  $config
     * @param  Closure(int, CarbonImmutable): float  $impressions  Expected impressions of the whole site on a day.
     * @param  list<array{query: string, weight: float, position: float, brand?: bool, peak?: int, season?: float, since?: int}>  $queries
     * @param  array{coverage?: float, rare?: float, first?: int, shift?: Closure(int, bool): float, indexed?: Closure(int): float}  $options
     */
    public function search(string $kind, string $label, array $config, Closure $impressions, array $queries, array $options = []): SampleSource
    {
        $timezone = $kind === 'gsc' ? 'America/Los_Angeles' : 'UTC';
        $today = $this->today($timezone);
        $first = $this->first($options['first'] ?? null);
        // Search Console's fresh data ends yesterday and turns final after about three days; Bing lags two days.
        $latest = $kind === 'gsc' ? 1 : 2;
        $noise = $this->noise->fork($kind.':'.$label);
        $shift = $options['shift'] ?? fn (int $ago, bool $brand): float => 0.0;
        $coverage = $options['coverage'] ?? 0.6;
        $rareShare = $options['rare'] ?? 0.32;
        $level = $noise->fork('level')->wander($first + 1, 0.08);
        $tail = $noise->fork('tail');
        $weights = array_sum(array_column($queries, 'weight'));
        $streams = [];
        $drift = [];
        foreach ($queries as $query) {
            $streams[$query['query']] = $noise->fork('query:'.$query['query']);
            $drift[$query['query']] = 0.0;
        }
        $daily = [];
        $rows = [];
        for ($ago = $first; $ago >= $latest; $ago--) {
            $date = $today->subDays($ago);
            $expected = $impressions($ago, $date) * $level[$first - $ago];
            $day = [];
            foreach ($queries as $query) {
                $text = $query['query'];
                $random = $streams[$text];
                $brand = $query['brand'] ?? false;
                $drift[$text] = 0.93 * $drift[$text] + ($brand ? 0.04 : 0.22) * $random->normal();
                $position = max(1.0, $query['position'] + $drift[$text] + $shift($ago, $brand));
                $seasonal = isset($query['peak']) ? Calendar::season($date, $query['season'] ?? 0.5, $query['peak']) : 1.0;
                $live = $ago <= ($query['since'] ?? PHP_INT_MAX) ? 1.0 : 0.0;
                $shown = $random->poisson($expected * $coverage * $query['weight'] / $weights * $seasonal * $live);
                $clicked = min($shown, $random->poisson($shown * min(0.7, self::ctr($position) * ($brand ? 2.2 : 1.0))));
                if ($shown > 0) {
                    $day[$text] = [$clicked, $shown, $position];
                }
            }
            $rare = $tail->poisson($expected * (1 - $coverage));
            $topClicks = array_sum(array_column($day, 0));
            $rareClicks = min($rare, $tail->poisson($topClicks * $rareShare / (1 - $rareShare) * self::ctr(12 + $shift($ago, false)) / self::ctr(12)));
            $rows[$date->toDateString()] = $day;
            $partial = $kind === 'gsc' && $ago < 3;
            $daily[] = $this->row('impressions', $date, array_sum(array_column($day, 1)) + $rare, $partial);
            $daily[] = $this->row('clicks', $date, $topClicks + $rareClicks, $partial);
            if ($kind === 'bing' && isset($options['indexed'])) {
                $daily[] = $this->row('indexed_pages', $date, (int) round($options['indexed']($ago)), false);
            }
        }
        if ($kind === 'gsc') {
            $result = new CollectionResult(daily: $daily, queries: $this->gscWindows($rows, $today->subDays($latest), $today->subDays(3)),
                queryMonths: $this->gscMonths($rows, $today->subDays(3)));
        } else {
            $weeks = $this->weeks($rows);
            $result = new CollectionResult(daily: $daily, queries: $this->bingWindows($weeks), queryMonths: $this->bingMonths($weeks));
        }

        return $this->source($kind, $label, $config, $timezone, [$result], 23);
    }

    /**
     * WordPress.org downloads: a weekly rhythm plus automatic updates after every release, spread over the following week.
     *
     * @param  Closure(int, CarbonImmutable): float  $downloads  Expected downloads on a day without a release.
     * @param  array<int, float>  $releases  Extra downloads (installed sites updating) by days ago of the release.
     * @param  list<int>  $gaps  Days WordPress.org did not report.
     */
    public function downloads(string $slug, Closure $downloads, array $releases, array $gaps = []): SampleSource
    {
        $today = $this->today('UTC');
        $first = $this->first(null);
        $noise = $this->noise->fork('wporg:'.$slug);
        $level = $noise->fork('level')->wander($first + 1, 0.07);
        $updates = [0.22, 0.34, 0.17, 0.1, 0.07, 0.05, 0.05];
        $daily = [];
        for ($ago = $first; $ago >= 0; $ago--) {
            $date = $today->subDays($ago);
            $expected = $downloads($ago, $date) * $level[$first - $ago];
            foreach ($releases as $released => $size) {
                $expected += ($released - $ago >= 0 ? ($updates[$released - $ago] ?? 0) : 0) * $size;
            }
            $count = $noise->poisson($expected * ($ago === 0 ? $this->elapsed('UTC') : 1.0));
            if (! in_array($ago, $gaps, true)) {
                $daily[] = $this->row('downloads', $date, $count, $ago === 0);
            }
        }

        return $this->source('wporg', $slug, ['slug' => $slug, 'limit' => 365], 'UTC', [new CollectionResult(daily: $daily)], 41);
    }

    /**
     * A GitHub repository: bursty commits (quiet, normal and busy stretches; pushes before releases), merged pull requests,
     * lines changed, open issues since the source was connected, releases and optional deployments.
     *
     * Options: prs: merged pull requests per commit; loc: median lines changed per commit.
     *
     * @param  Closure(int, CarbonImmutable): float  $commits  Expected commits on an ordinary day of normal activity.
     * @param  array{repository: string, prs: float, loc: float, issues: array{start: int, opened: float, closed: float}, releases: list<array{ago: int, tag: string, notes?: string, prerelease?: bool}>, connected?: int, deployments?: array{environment: string, chance: float}}  $options
     */
    public function github(string $label, Closure $commits, array $options): SampleSource
    {
        $repository = $options['repository'];
        [$daily, $activity] = $this->commits('github:'.$repository, $commits, $options['releases'], $options['prs'], $options['loc'], true);
        $daily = [...$daily, ...$this->issues('github:'.$repository, $options['issues'], $activity, $options['releases'], $options['connected'] ?? null)];
        $events = [];
        foreach ($options['releases'] as $release) {
            $at = $this->moment($release['ago'], 'release:'.$release['tag'], 13, 17);
            $events[] = ['origin' => 'github_release', 'external_id' => 'RE_'.substr(hash('sha256', $repository.$release['tag']), 0, 20),
                'title' => $release['tag'].(($release['prerelease'] ?? false) ? ' (prerelease)' : ''), 'body' => $release['notes'] ?? '',
                'url' => 'https://github.example/'.$repository.'/releases/tag/'.$release['tag'], 'occurred_at' => $at->toIso8601String()];
        }
        $config = ['repository' => $repository, 'days' => 180];
        if (isset($options['deployments'])) {
            $environment = $options['deployments']['environment'];
            $config['environments'] = [$environment];
            $tags = array_column($options['releases'], 'tag', 'ago');
            $random = $this->noise->fork('deployments:'.$repository);
            foreach ($activity as $ago => $count) {
                $deploy = $random->chance($options['deployments']['chance']);
                if (($count === 0 || ! $deploy) && ! isset($tags[$ago])) {
                    continue;
                }
                $at = $this->moment($ago, 'deploy:'.$repository, 9, 18);
                if ($at->gt($this->now)) {
                    continue;
                }
                $run = 7_400_000_000 + crc32($repository.$ago) % 90_000_000;
                $events[] = ['origin' => 'github_deployment', 'external_id' => $environment.':'.$run, 'title' => $environment.' · '.($tags[$ago] ?? substr(hash('sha1', $repository.$ago), 0, 7)),
                    'body' => '', 'url' => 'https://github.example/'.$repository.'/actions/runs/'.$run, 'occurred_at' => $at->toIso8601String()];
            }
        }

        return $this->source('github', $label, $config, 'UTC', [new CollectionResult(daily: $daily, events: $events)], 17);
    }

    /**
     * An Azure DevOps repository: commits and completed pull requests, default-branch pipeline runs (a nightly run plus one per
     * merge; failures cluster when tests turn flaky), open bugs, and deployments to one environment.
     *
     * @param  Closure(int, CarbonImmutable): float  $commits
     * @param  array{repository: string, organization: string, prs: float, loc: float, issues: array{start: int, opened: float, closed: float}, connected?: int, failure: float, flaky?: array{0: int, 1: int}, deployments: array{environment: string, weekdays: list<int>}}  $options
     */
    public function azure(string $label, Closure $commits, array $options): SampleSource
    {
        $repository = $options['repository'];
        [$daily, $activity, $merged] = $this->commits('azure:'.$repository, $commits, [], $options['prs'], $options['loc'], false);
        $daily = [...$daily, ...$this->issues('azure:'.$repository, $options['issues'], $activity, [], $options['connected'] ?? null)];
        $today = $this->today('UTC');
        $random = $this->noise->fork('pipelines:'.$repository);
        $events = [];
        $build = 41_000;
        foreach ($merged as $ago => $pulls) {
            $date = $today->subDays($ago);
            $runs = $pulls + ($date->isWeekday() ? 1 : 0);
            [$from, $to] = $options['flaky'] ?? [-1, -1];
            $rate = $ago <= $from && $ago >= $to ? 0.55 : $options['failure'];
            $failed = 0;
            for ($run = 0; $run < $runs; $run++) {
                $failed += $random->chance($rate) ? 1 : 0;
            }
            $daily[] = $this->row('pipeline_runs_succeeded', $date, $runs - $failed, $ago === 0);
            $daily[] = $this->row('pipeline_runs_failed', $date, $failed, $ago === 0);
            $build += $runs;
            if (in_array($date->dayOfWeekIso, $options['deployments']['weekdays'], true) && $ago > 0 && $activity[$ago] + ($activity[$ago + 1] ?? 0) > 0) {
                $version = $date->isoWeekYear().'.'.$date->isoWeek().'.'.($date->dayOfWeekIso === $options['deployments']['weekdays'][0] ? 1 : 2);
                $at = $this->moment($ago, 'azure-deploy', 10, 16);
                $events[] = ['origin' => 'azure_devops_deployment', 'external_id' => (string) $build, 'title' => $options['deployments']['environment'].' · '.$version, 'body' => '',
                    'url' => 'https://dev.azure.example/'.$options['organization'].'/'.rawurlencode(explode('/', $repository)[0]).'/_build/results?buildId='.$build, 'occurred_at' => $at->toIso8601String()];
            }
        }

        return $this->source('azure_devops', $label, ['repository' => $repository, 'days' => 180, 'environments' => [$options['deployments']['environment']]], 'UTC',
            [new CollectionResult(daily: $daily, events: $events)], 26);
    }

    /**
     * UptimeRobot snapshots, one collection per day since the monitor was connected: status, trailing 24-hour uptime and
     * average response time. Incidents are minutes of downtime on a day.
     *
     * @param  array{monitor: string, connected: int, response: float, steps?: Closure(int): float, incidents?: array<int, int>}  $options
     */
    public function uptime(string $label, array $options): SampleSource
    {
        $today = $this->today('UTC');
        $first = min($options['connected'], $this->days - 1);
        $noise = $this->noise->fork('uptime:'.$label);
        $level = $noise->fork('level')->wander($first + 1, 0.07, 0.75);
        $collections = [];
        for ($ago = $first; $ago >= 0; $ago--) {
            $date = $today->subDays($ago);
            $down = $options['incidents'][$ago] ?? ($noise->chance(0.03) ? $noise->int(1, 3) : 0);
            $response = $options['response'] * ($options['steps'] ?? fn (int $ago): float => 1.0)($ago) * $level[$first - $ago] * ($down > 10 ? 1.3 : 1.0);
            $at = $ago === 0 ? $this->now->subMinutes(4)->max($today) : $date->setTime(23, 55);
            $snapshot = $at->toDateString();
            $collections[] = ['result' => new CollectionResult(daily: [
                $this->row('monitor_status', $at, 2, false),
                ['metric' => 'uptime_24h', 'date' => $snapshot, 'value' => round(100 - $down / 14.4, 3), 'is_partial' => false],
                ['metric' => 'response_time_24h', 'date' => $snapshot, 'value' => round($response, 1), 'is_partial' => false],
            ], replaceDailyDates: [$snapshot => ['monitor_status', 'uptime_24h', 'response_time_24h']]), 'at' => $at];
        }

        return new SampleSource('uptimerobot', $label, ['monitor_id' => $options['monitor']], 'UTC', $collections, $this->now->subMinutes(4));
    }

    /**
     * Daily Lighthouse lab tests since the source was connected, and Chrome UX Report field data: weekly 28-day history from
     * before the connection, then the daily record. Field values average the days in their window, so they trail a deploy
     * that the lab picks up at once.
     *
     * Options: field: url, origin (only site-wide data) or none (too little traffic).
     *
     * @param  Closure(int): array{lcp: float, tbt: float, cls: float}  $lab  Expected lab results on a day.
     * @param  array{url: string, strategy: string, connected: int, field: string, field_lcp?: float, inp?: float}  $options
     */
    public function pageSpeed(Closure $lab, array $options): SampleSource
    {
        $today = $this->today('UTC');
        $first = min($options['connected'], $this->days - 1);
        $strategy = $options['strategy'];
        $url = $options['url'];
        $noise = $this->noise->fork('pagespeed:'.$url.$strategy);
        $tests = $noise->fork('lab');
        $status = $options['field'] === 'none' ? 'no_data' : 'available';
        $daily = [];
        $replace = [];
        for ($ago = $first; $ago >= 0; $ago--) {
            $expected = $lab($ago);
            $lcp = $expected['lcp'] * $tests->lognormal(1, 0.07);
            $tbt = $expected['tbt'] * $tests->lognormal(1, 0.25);
            $cls = $expected['cls'] * ($tests->chance(0.1) ? 1.5 : $tests->lognormal(1, 0.05));
            $fcp = $lcp * $tests->between(0.5, 0.62);
            $speed = $lcp * $tests->between(0.95, 1.2);
            $tested = $ago === 0 ? $this->now->subMinutes(97) : $today->subDays($ago)->setTime(5, $tests->int(2, 50));
            if ($tested->lt($today->subDays($ago))) {
                continue; // Today's test has not run yet.
            }
            $context = ['type' => 'lab', 'url' => $url, 'audited_url' => $url, 'device' => $strategy, 'lighthouse_version' => '12.8.0', 'tested_at' => $tested->toIso8601String(),
                'field_status' => $status, 'history_status' => 'available'];
            $values = ['lab_score' => round(100 * self::lighthouse($strategy, $fcp, $speed, $lcp, $tbt, $cls)), 'lab_lcp' => round($lcp, 1), 'lab_cls' => round($cls, 4), 'lab_tbt' => round($tbt)];
            foreach ($values as $metric => $value) {
                $daily[] = ['metric' => $metric, 'date' => $tested->toDateString(), 'value' => (float) $value, 'is_partial' => false, 'context' => $context];
            }
            $replace[$tested->toDateString()] = array_keys($values);
        }
        if ($options['field'] !== 'none') {
            $prefix = $options['field'] === 'origin' ? 'crux_origin_' : 'crux_';
            $target = $options['field'] === 'origin' ? 'https://'.parse_url($url, PHP_URL_HOST) : $url;
            $field = $noise->fork('field');
            $underlying = [];
            $oldest = $first + 2 + 39 * 7 + 27;
            for ($ago = $oldest; $ago >= 0; $ago--) {
                $expected = $lab($ago);
                $underlying[$ago] = ['lcp' => $expected['lcp'] * ($options['field_lcp'] ?? 0.62) * $field->lognormal(1, 0.04),
                    'inp' => ($options['inp'] ?? 160) * (1 + 0.4 * $expected['tbt'] / 600) * $field->lognormal(1, 0.05), 'cls' => $expected['cls'] * 0.8 * $field->lognormal(1, 0.06)];
            }
            // Weekly history before the connection, then one record a day; CrUX's latest window ends two days back.
            $ends = [];
            for ($week = 39; $week >= 0; $week--) {
                $ends[] = $first + 2 + 7 * $week;
            }
            for ($ago = $first - 1; $ago >= 0; $ago--) {
                $ends[] = $ago + 2;
            }
            foreach ($ends as $end) {
                $window = array_slice($underlying, $oldest - $end - 27, 28, true);
                $start = $today->subDays($end + 27);
                $last = $today->subDays($end);
                $context = ['type' => 'field', 'scope' => $options['field'], 'url' => $target, 'device' => $strategy, 'percentile' => 75,
                    'collection_start' => $start->toDateString(), 'collection_end' => $last->toDateString()];
                foreach (['lcp' => 0, 'inp' => 0, 'cls' => 2] as $name => $decimals) {
                    $daily[] = ['metric' => $prefix.$name, 'date' => $last->toDateString(), 'value' => round(array_sum(array_column($window, $name)) / count($window), $decimals),
                        'is_partial' => false, 'window_start' => $start->toIso8601String(), 'window_end' => $last->endOfDay()->toIso8601String(), 'context' => $context];
                }
                $replace[$last->toDateString()] = array_merge($replace[$last->toDateString()] ?? [], [$prefix.'lcp', $prefix.'inp', $prefix.'cls']);
            }
        }

        return new SampleSource('pagespeed', $url, ['url' => $url, 'strategy' => $strategy], 'UTC', [['result' => new CollectionResult(daily: $daily, replaceDailyDates: $replace), 'at' => $this->now->subMinutes(97)]],
            $this->now->subMinutes(97));
    }

    /** Organic click-through rate at an average position. */
    public static function ctr(float $position): float
    {
        return 0.3 * max(1.0, $position) ** -1.25;
    }

    /** Lighthouse 10+ performance score (0–1) from its log-normal metric curves and weights. */
    public static function lighthouse(string $strategy, float $fcp, float $speedIndex, float $lcp, float $tbt, float $cls): float
    {
        $curves = $strategy === 'desktop'
            ? ['fcp' => [934, 1600], 'si' => [1311, 2300], 'lcp' => [1200, 2400], 'tbt' => [150, 350], 'cls' => [0.1, 0.25]]
            : ['fcp' => [1800, 3000], 'si' => [3387, 5800], 'lcp' => [2500, 4000], 'tbt' => [200, 600], 'cls' => [0.1, 0.25]];
        $score = fn (float $value, array $curve): float => $value <= 0 ? 1.0
            : 0.5 * self::erfc((log($value) - log($curve[1])) / ((log($curve[1]) - log($curve[0])) / 1.2815515655446004) / M_SQRT2);

        return 0.1 * $score($fcp, $curves['fcp']) + 0.1 * $score($speedIndex, $curves['si']) + 0.25 * $score($lcp, $curves['lcp'])
            + 0.3 * $score($tbt, $curves['tbt']) + 0.25 * $score($cls, $curves['cls']);
    }

    /** Complementary error function (Abramowitz and Stegun 7.1.26). */
    private static function erfc(float $x): float
    {
        $t = 1 / (1 + 0.3275911 * abs($x));
        $erf = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return 1 - ($x >= 0 ? $erf : -$erf);
    }

    /**
     * Daily commits, merged pull requests and lines changed. Activity switches between quiet, normal and busy stretches,
     * and picks up in the days before a release.
     *
     * @param  Closure(int, CarbonImmutable): float  $expected
     * @param  list<array{ago: int, tag: string, notes?: string, prerelease?: bool}>  $releases
     * @return array{0: list<array{metric: string, date: string, value: float, is_partial: bool}>, 1: array<int, int>, 2: array<int, int>}
     */
    private function commits(string $stream, Closure $expected, array $releases, float $prs, float $loc, bool $lines): array
    {
        $today = $this->today('UTC');
        $first = $this->first(null);
        $noise = $this->noise->fork($stream);
        $releaseDays = array_column($releases, 'ago');
        $mood = 1.0;
        $daily = [];
        $activity = [];
        $merged = [];
        for ($ago = $first; $ago >= 0; $ago--) {
            $date = $today->subDays($ago);
            if ($noise->chance(0.18)) {
                $mood = [0.25, 1.0, 1.0, 1.9][$noise->int(0, 3)];
            }
            $push = array_filter($releaseDays, fn (int $release): bool => $ago - $release > 0 && $ago - $release <= 3) ? 1.8 : 1.0;
            $rate = $expected($ago, $date) * $mood * $push * ($ago === 0 ? $this->elapsed('UTC') : 1.0);
            $pulls = $noise->poisson($rate * $prs);
            // Squash merges leave one commit per pull request; direct pushes add the rest.
            $count = max($prs >= 0.6 ? $pulls : 0, $noise->poisson($rate));
            $changed = 0;
            for ($commit = 0; $commit < $count; $commit++) {
                $changed += (int) min(3000, round($noise->lognormal($loc, 1.1)));
            }
            $activity[$ago] = $count;
            $merged[$ago] = $pulls;
            $daily[] = $this->row('commits', $date, $count, $ago === 0);
            $daily[] = $this->row('prs_merged', $date, $pulls, $ago === 0);
            if ($lines) {
                $daily[] = $this->row('loc_changed', $date, $changed, $ago === 0);
            }
        }

        return [$daily, $activity, $merged];
    }

    /**
     * Open issues observed once a day since the source was connected; a release closes a batch of them.
     *
     * @param  array{start: int, opened: float, closed: float}  $issues
     * @param  array<int, int>  $activity
     * @param  list<array{ago: int, tag: string, notes?: string, prerelease?: bool}>  $releases
     * @return list<array{metric: string, date: string, value: float, is_partial: bool}>
     */
    private function issues(string $stream, array $issues, array $activity, array $releases, ?int $connected): array
    {
        $today = $this->today('UTC');
        $noise = $this->noise->fork($stream.':issues');
        $average = max(1, array_sum($activity) / max(1, count($activity)));
        $open = $issues['start'];
        $releaseDays = array_column($releases, 'ago');
        $connected = min($connected ?? 180, $this->days - 1);
        $rows = [];
        foreach ($activity as $ago => $commits) {
            $busy = 0.6 + 0.4 * min(2.5, $commits / $average);
            $open += $noise->poisson($issues['opened'] * ($today->subDays($ago)->isWeekend() ? 0.6 : 1.1));
            $open -= min($open, $noise->poisson($issues['closed'] * $busy * (in_array($ago, $releaseDays, true) ? 3 : 1)));
            if ($ago <= $connected) {
                $rows[] = $this->row('open_issues', $today->subDays($ago), $open, false);
            }
        }

        return $rows;
    }

    /**
     * Search Console's 7, 30 and 90-day query windows ending on the latest reported date, with the window before each.
     *
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $rows
     * @return list<array{days: int, previous: bool, start_date: string, end_date: string, query: string, clicks: float, impressions: float, position: float|null, is_partial: bool}>
     */
    private function gscWindows(array $rows, CarbonImmutable $end, CarbonImmutable $boundary): array
    {
        $out = [];
        foreach ([7, 30, 90] as $days) {
            $start = $end->subDays($days - 1);
            $previousEnd = $start->subDay();
            $current = $this->sum($rows, $start, $end, GscConnector::QUERY_LIMIT);
            $previous = array_intersect_key($this->sum($rows, $previousEnd->subDays($days - 1), $previousEnd, 2000), $current);
            foreach ([[$current, false, $start, $end], [$previous, true, $previousEnd->subDays($days - 1), $previousEnd]] as [$set, $isPrevious, $from, $to]) {
                foreach ($set as $text => $row) {
                    $out[] = ['days' => $days, 'previous' => $isPrevious, 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'query' => (string) $text] + $row
                        + ['is_partial' => $to->gt($boundary)];
                }
            }
        }

        return $out;
    }

    /**
     * Complete calendar months whose data is final, newest first, as Search Console archives them.
     *
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $rows
     * @return list<array{month: string, start_date: string, end_date: string, weeks: int|null, rows: list<array{query: string, clicks: float, impressions: float, position: float|null}>}>
     */
    private function gscMonths(array $rows, CarbonImmutable $boundary): array
    {
        $months = [];
        $oldest = (string) array_key_first($rows);
        for ($month = $boundary->addDay()->startOfMonth()->subMonthNoOverflow(); count($months) < GscConnector::ARCHIVE_MONTHS && $month->toDateString() >= $oldest; $month = $month->subMonthNoOverflow()) {
            $end = $month->endOfMonth()->startOfDay();
            $list = [];
            foreach ($this->sum($rows, $month, $end, GscConnector::MONTH_LIMIT) as $text => $row) {
                $list[] = ['query' => (string) $text] + $row;
            }
            $months[] = ['month' => $month->toDateString(), 'start_date' => $month->toDateString(), 'end_date' => $end->toDateString(), 'weeks' => null, 'rows' => $list];
        }

        return $months;
    }

    /**
     * Daily query rows grouped into complete Monday-to-Sunday weeks, dated by their Monday, as Bing reports them.
     *
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $rows
     * @return array<string, array<string, array{0: int, 1: int, 2: float}>>
     */
    private function weeks(array $rows): array
    {
        $weeks = [];
        $dates = array_keys($rows);
        $first = CarbonImmutable::parse($dates[0]);
        $last = CarbonImmutable::parse(end($dates));
        for ($monday = $first->dayOfWeekIso === 1 ? $first : $first->next(CarbonImmutable::MONDAY); $monday->addDays(6)->lte($last); $monday = $monday->addWeek()) {
            $weeks[$monday->toDateString()] = [];
            foreach ($this->sum($rows, $monday, $monday->addDays(6), PHP_INT_MAX) as $text => $row) {
                $weeks[$monday->toDateString()][$text] = [(int) $row['clicks'], (int) $row['impressions'], (float) $row['position']];
            }
        }

        return $weeks;
    }

    /**
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $weeks
     * @return list<array{days: int, previous: bool, start_date: string, end_date: string, query: string, clicks: float, impressions: float, position: float|null, is_partial: bool}>
     */
    private function bingWindows(array $weeks): array
    {
        if ($weeks === []) {
            return [];
        }
        $end = CarbonImmutable::parse((string) array_key_last($weeks));
        $out = [];
        foreach (BingConnector::QUERY_WEEKS as $days => $count) {
            $start = $end->subDays(7 * $count - 1);
            $previousEnd = $start->subDay();
            $current = $this->sum($weeks, $start, $end, GscConnector::QUERY_LIMIT);
            $previous = array_intersect_key($this->sum($weeks, $previousEnd->subDays(7 * $count - 1), $previousEnd, GscConnector::QUERY_LIMIT), $current);
            foreach ([[$current, false, $start, $end], [$previous, true, $previousEnd->subDays(7 * $count - 1), $previousEnd]] as [$set, $isPrevious, $from, $to]) {
                foreach ($set as $text => $row) {
                    $out[] = ['days' => $days, 'previous' => $isPrevious, 'start_date' => $from->toDateString(), 'end_date' => $to->toDateString(), 'query' => (string) $text] + $row + ['is_partial' => false];
                }
            }
        }

        return $out;
    }

    /**
     * Months whose weeks are all reported, newest first, as BingConnector archives them.
     *
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $weeks
     * @return list<array{month: string, start_date: string, end_date: string, weeks: int|null, rows: list<array{query: string, clicks: float, impressions: float, position: float|null}>}>
     */
    private function bingMonths(array $weeks): array
    {
        $dates = array_keys($weeks);
        if ($dates === []) {
            return [];
        }
        $latest = end($dates);
        $byMonth = [];
        foreach ($dates as $date) {
            $byMonth[substr($date, 0, 7)][] = $date;
        }
        $months = [];
        foreach ($byMonth as $month => $monthDates) {
            $start = CarbonImmutable::parse($month.'-01');
            if ($latest <= $start->endOfMonth()->toDateString() || ($month === array_key_first($byMonth) && $monthDates[0] > $start->addDays(6)->toDateString())) {
                continue;
            }
            $list = [];
            foreach ($this->sum($weeks, CarbonImmutable::parse($monthDates[0]), CarbonImmutable::parse(end($monthDates)), GscConnector::MONTH_LIMIT) as $text => $row) {
                $list[] = ['query' => (string) $text] + $row;
            }
            $months[] = ['month' => $start->toDateString(), 'start_date' => $monthDates[0], 'end_date' => end($monthDates), 'weeks' => count($monthDates), 'rows' => $list];
        }

        return array_reverse($months);
    }

    /**
     * Query totals between two dates: clicks and impressions summed, position weighted by impressions; most clicks first.
     *
     * @param  array<string, array<string, array{0: int, 1: int, 2: float}>>  $rows
     * @return array<string, array{clicks: float, impressions: float, position: float|null}>
     */
    private function sum(array $rows, CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $totals = [];
        foreach ($rows as $date => $queries) {
            if ($date < $from->toDateString() || $date > $to->toDateString()) {
                continue;
            }
            foreach ($queries as $text => [$clicks, $impressions, $position]) {
                $total = $totals[$text] ?? [0, 0, 0.0];
                $totals[$text] = [$total[0] + $clicks, $total[1] + $impressions, $total[2] + $position * $impressions];
            }
        }
        uasort($totals, fn (array $a, array $b): int => [$b[0], $b[1]] <=> [$a[0], $a[1]]);

        return array_map(fn (array $total): array => ['clicks' => (float) $total[0], 'impressions' => (float) $total[1],
            'position' => $total[1] > 0 ? round($total[2] / $total[1], 2) : null], array_slice($totals, 0, $limit, true));
    }

    /** A time on the day $ago days back, between two hours, the same for the same seed. */
    private function moment(int $ago, string $stream, int $from, int $to): CarbonImmutable
    {
        $noise = $this->noise->fork($stream.':'.$ago);

        return $this->today('UTC')->subDays($ago)->setTime($noise->int($from, $to - 1), $noise->int(0, 59), $noise->int(0, 59));
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<CollectionResult>  $results
     */
    private function source(string $kind, string $label, array $config, string $timezone, array $results, int $minutes): SampleSource
    {
        $at = $this->now->subMinutes($minutes);

        return new SampleSource($kind, $label, $config, $timezone, array_map(fn (CollectionResult $result): array => ['result' => $result, 'at' => $at], $results), $at);
    }

    /** @return array{metric: string, date: string, value: float, is_partial: bool} */
    private function row(string $metric, CarbonImmutable $date, int|float $value, bool $partial): array
    {
        return ['metric' => $metric, 'date' => $date->toDateString(), 'value' => (float) $value, 'is_partial' => $partial];
    }

    private function today(string $timezone): CarbonImmutable
    {
        return $this->now->setTimezone($timezone)->startOfDay();
    }

    /** Share of the provider's day that has passed, for today's provisional counts. */
    private function elapsed(string $timezone): float
    {
        $local = $this->now->setTimezone($timezone);

        return max(0.04, ($local->hour * 60 + $local->minute) / 1440);
    }

    private function first(?int $first): int
    {
        return min($first ?? $this->days - 1, $this->days - 1);
    }
}
