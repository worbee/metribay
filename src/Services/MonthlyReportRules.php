<?php

declare(strict_types=1);

namespace Metribay\Core\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Number;
use Metribay\Core\Metrics\MetricRegistry;
use Metribay\Core\Metrics\ProjectLayout;

/**
 * Deterministic summary and actions from a frozen snapshot, rendered in the current language.
 * Outcomes lead, a small move never does, and a cause is only ever "likely". See docs/MONTHLY_REPORTS.md.
 *
 * Every sentence and action is a candidate with a stable id, so a host may reorder them (MonthlyReportNarrative)
 * without writing text of its own.
 */
final class MonthlyReportRules
{
    public const MAX_SENTENCES = 3;

    public const MAX_ACTIONS = 3;

    /** Outcomes first, then audience. Any other metric needs a larger move to lead. */
    public const TIERS = [
        1 => ['paddle_revenue', 'store_revenue', 'paddle_mrr', 'paddle_active_subscribers', 'store_orders', 'conversions', 'key_events'],
        2 => ['visitors', 'visits', 'active_users', 'clicks', 'downloads', 'release_downloads', 'followers'],
    ];

    /** Metrics that tell the same story: the second sentence never repeats the headline's family. */
    private const FAMILIES = [
        'revenue' => ['paddle_revenue', 'paddle_mrr', 'paddle_active_subscribers', 'store_revenue', 'store_orders'],
        'audience' => ['visitors', 'visits', 'pageviews', 'active_users'],
        'search' => ['clicks', 'impressions'],
    ];

    /** Figures at the top of the page, in this order. */
    public const KPI_KEYS = ['paddle_revenue', 'store_revenue', 'paddle_mrr', 'store_orders', 'conversions', 'key_events', 'visitors', 'visits', 'active_users', 'clicks', 'downloads', 'followers', 'commits', 'published_items'];

    /** Rates, rolling 28-day field data, uptime and counts that describe work rather than results never lead a summary. */
    private const NEVER_LEAD = ['bounce_rate', 'avg_visit_duration', 'uptime_24h', 'crux_lcp', 'crux_inp', 'crux_cls', 'crux_origin_lcp', 'crux_origin_inp', 'crux_origin_cls',
        'open_issues', 'loc_changed', 'published_urls', 'indexed_pages', 'pipeline_runs_succeeded'];

    private const KPI_LIMIT = 6;

    /** Collection and uptime problems always stay, whatever a host's ranking says. */
    private const MANDATORY = ['failing', 'paused', 'downtime', 'reconnect', 'resume', 'outage'];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{sentences?: list<string>, actions?: list<string>}|null  $ranking  Candidate ids in the order a host chose.
     * @return array{sentences: list<string>, actions: list<array{title: string, detail: string, evidence: string}>, headline: string, change: string, problem: ?string}
     *                                                                                                                                                                   The change is the first sentence about a figure, the problem the first collection or uptime problem.
     */
    public function read(array $payload, ?array $ranking = null): array
    {
        $built = $this->build($payload);
        $sentences = $this->pick($built['sentences'], self::MAX_SENTENCES, $ranking['sentences'] ?? null);
        $actions = $this->pick($built['actions'], self::MAX_ACTIONS, $ranking['actions'] ?? null);
        $text = array_map(fn (array $candidate): string => $candidate['text'], $sentences);
        $problems = array_values(array_filter($built['sentences'], fn (array $candidate): bool => in_array($candidate['facts']['kind'] ?? null, self::MANDATORY, true)));
        $changes = array_values(array_filter($sentences, fn (array $candidate): bool => ! in_array($candidate['facts']['kind'] ?? null, self::MANDATORY, true)));

        return [
            'sentences' => $text,
            'actions' => array_map(fn (array $candidate): array => ['title' => $candidate['title'], 'detail' => $candidate['detail'], 'evidence' => $candidate['evidence']], $actions),
            'headline' => $text[0] ?? '',
            'change' => $changes[0]['text'] ?? ($text[0] ?? ''),
            'problem' => $problems[0]['text'] ?? null,
        ];
    }

    /**
     * Candidate ids with the facts behind them and no names, queries or titles: what a host may rank.
     *
     * @param  array<string, mixed>  $payload
     * @return array{sentences: list<array<string, mixed>>, actions: list<array<string, mixed>>}
     */
    public function candidates(array $payload): array
    {
        $built = $this->build($payload);
        $strip = fn (array $candidate): array => ['id' => $candidate['id'], 'weight' => $candidate['weight']] + $candidate['facts'];

        return ['sentences' => array_map($strip, $built['sentences']), 'actions' => array_map($strip, $built['actions'])];
    }

    /**
     * The figures for the top of the page: outcomes first, at most six.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{source: array<string, mixed>, figure: array<string, mixed>, label: string}> Key events are named after their data source.
     */
    public function kpis(array $payload): array
    {
        $tiles = [];
        foreach (self::KPI_KEYS as $key) {
            $taken = 0;
            foreach ($payload['sources'] as $source) {
                foreach ($source['metrics'] as $figure) {
                    if ($figure['key'] !== $key || $figure['value'] === null || $taken >= ($key === 'key_events' ? 2 : 1)) {
                        continue;
                    }
                    $tiles[] = ['source' => $source, 'figure' => $figure, 'label' => $key === 'key_events' ? (string) $source['label'] : __(MetricRegistry::get($key)->label)];
                    $taken++;
                    if (count($tiles) === self::KPI_LIMIT) {
                        return $tiles;
                    }
                }
            }
        }

        return $tiles;
    }

    /**
     * A figure against one of its comparisons, as a fraction. Totals over months of different lengths, or with missing
     * days, are compared per known day. Null when the comparison would mislead: an unknown or zero base, a provider
     * total for part of a month, or less than half a month known.
     *
     * @param  array<string, mixed>  $figure
     * @return array{change: float, per_day: bool}|null
     */
    public function compare(array $figure, string $against): ?array
    {
        $value = $figure['value'] ?? null;
        $other = $figure[$against] ?? null;
        if ($value === null || $other === null || (float) $other === 0.0) {
            return null;
        }
        $value = (float) $value;
        $other = (float) $other;
        $prefix = ['previous' => 'previous_', 'year_ago' => 'year_', 'year_ago_previous' => 'year_previous_'][$against] ?? null;
        $valuePartial = (bool) ($figure['partial'] ?? false);
        $otherPartial = $prefix !== null && (bool) ($figure[$prefix.'partial'] ?? false);
        $perDay = false;
        if ($figure['aggregation'] === 'period' && ($valuePartial || $otherPartial)) {
            return null;
        }
        if ($figure['aggregation'] === 'sum') {
            $days = (int) $figure['days_in_month'];
            $known = (int) ($figure['days_known'] ?? $days);
            if ($prefix === null) {
                if ($valuePartial) {
                    return null;
                }
            } else {
                $otherDays = (int) ($figure[$prefix.'days'] ?? $days);
                $otherKnown = (int) ($figure[$prefix.'known'] ?? $otherDays);
                if ($valuePartial || $otherPartial) {
                    if (in_array($figure['key'], ['published_items', 'new_urls'], true) || $known < $days / 2 || $otherKnown < $otherDays / 2) {
                        return null;
                    }
                    [$value, $other, $perDay] = [$value / max(1, $known), $other / max(1, $otherKnown), true];
                } elseif (abs($days - $otherDays) / $days > 0.05) {
                    [$value, $other, $perDay] = [$value / $days, $other / $otherDays, true];
                }
            }
        }

        return ['change' => ($value - $other) / abs($other), 'per_day' => $perDay];
    }

    /**
     * A comparison as the page shows it: a signed percentage, or the signed difference when the base is a small count
     * (4 against 0 is "+4", not a percentage). Moves under half a percent are neither good nor bad.
     *
     * @param  array<string, mixed>  $figure
     * @return array{text: string, good: ?bool, per_day: bool}|null
     */
    public function difference(array $figure, string $against): ?array
    {
        $value = $figure['value'] ?? null;
        $other = $figure[$against] ?? null;
        if ($value !== null && $other !== null && in_array($figure['unit'], ['count', 'lines'], true) && abs((float) $other) < 20 && ! ($figure['partial'] ?? false)) {
            $delta = round((float) $value - (float) $other);

            return ['text' => ($delta < 0 ? '−' : '+').Number::format(abs($delta), 0, locale: app()->getLocale()), 'good' => $delta == 0 ? null : $this->good($figure['key'], $delta), 'per_day' => false];
        }
        $comparison = $this->compare($figure, $against);
        if ($comparison === null) {
            return null;
        }

        return ['text' => $this->signed($comparison['change']), 'good' => abs($comparison['change']) < 0.005 ? null : $this->good($figure['key'], $comparison['change']), 'per_day' => $comparison['per_day']];
    }

    /** Whether a rise of this metric is good (true), bad (false) or neither (null). */
    public function good(string $key, float $change): ?bool
    {
        return match (ProjectLayout::polarity($key)) {
            'neutral' => null,
            'down' => $change < 0,
            default => $change > 0,
        };
    }

    /** @param  array<string, mixed>  $metric */
    public function number(array $metric, ?float $value): string
    {
        if ($value === null) {
            return '—';
        }
        if ($metric['unit'] === 'currency' && is_string($metric['currency'] ?? null)) {
            return Number::currency($value, $metric['currency'], locale: app()->getLocale());
        }
        if ($metric['unit'] === 'percent') {
            return Number::format($value, 2, locale: app()->getLocale()).'%';
        }
        if (in_array($metric['unit'], ['count', 'lines'], true)) {
            $value = round($value);
        }
        [$number, $suffix] = ProjectLayout::format(abs($value), $metric['unit']);

        return ($value < 0 ? '−' : '').$number.($suffix ? (str_starts_with($suffix, '/') ? $suffix : ' '.$suffix) : '');
    }

    /** "12%", in the current language, without a sign. */
    public function percent(float $fraction): string
    {
        return Number::percentage(round(abs($fraction) * 100), 0, locale: app()->getLocale()) ?: (string) round(abs($fraction) * 100).'%';
    }

    /** "+12%" or "−12%". */
    public function signed(float $fraction): string
    {
        return (round($fraction * 100) < 0 ? '−' : '+').$this->percent($fraction);
    }

    public function monthLabel(string $month, string $format = 'F Y'): string
    {
        return CarbonImmutable::parse($month.'-01')->translatedFormat($format);
    }

    public function dayLabel(string $date): string
    {
        return CarbonImmutable::parse($date)->translatedFormat('j M');
    }

    /**
     * What a figure is called in a sentence: the metric, with the data source when the project has it twice.
     * Key events are named after their data source, which says which event it is.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $figure
     */
    public function subject(array $payload, array $source, array $figure): string
    {
        if ($figure['key'] === 'key_events') {
            return (string) $source['label'];
        }
        $label = __(MetricRegistry::get($figure['key'])->label);
        $twice = 0;
        foreach ($payload['sources'] as $other) {
            foreach ($other['metrics'] as $candidate) {
                if ($candidate['key'] === $figure['key'] && $candidate['value'] !== null) {
                    $twice++;
                }
            }
        }

        return $twice > 1 ? __(':metric on :source', ['metric' => $label, 'source' => $source['label']]) : $label;
    }

    /**
     * Events a report lists: notes and releases, one per date and title. Older payloads stored every event.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function events(array $payload, string $key = 'events'): array
    {
        $events = [];
        $seen = [];
        foreach ($payload[$key] ?? [] as $event) {
            $id = $event['date'].'|'.mb_strtolower((string) $event['title']);
            if (in_array($event['origin'] ?? '', MonthlySnapshot::EVENT_ORIGINS, true) && ! isset($seen[$id])) {
                $seen[$id] = true;
                $events[] = $event + ['recurring' => false, 'shifts' => []];
            }
        }
        usort($events, fn (array $a, array $b): int => [$a['date'], $a['title']] <=> [$b['date'], $b['title']]);

        return $events;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sentences: list<array<string, mixed>>, actions: list<array<string, mixed>>}
     */
    private function build(array $payload): array
    {
        $month = (string) $payload['month'];
        $previousMonth = CarbonImmutable::parse($month.'-01')->subMonth()->format('Y-m');
        $sentences = [];
        $actions = [];
        foreach ($payload['sources'] as $source) {
            $this->health($source, $month, $sentences, $actions);
        }
        $movers = $this->movers($payload);
        $headline = $this->leader($movers);
        if ($headline === null) {
            $sentences[] = $this->candidate('quiet', 100, ['kind' => 'quiet'], text: __('No figure moved much in :month.', ['month' => $this->monthLabel($month, 'F')]));
        } else {
            $sentences[] = $this->moveSentence('move', 100, $payload, $headline, $previousMonth);
            $context = $this->context($payload, $headline, $month, $previousMonth);
            if ($context !== null) {
                $sentences[] = $context['sentence'];
            }
            // A move back to normal or with the season needs no action.
            if ($headline['good'] === false && $headline['tier'] <= 2 && ! in_array($context['kind'] ?? null, ['normal', 'season'], true)) {
                $actions[] = $this->investigate($payload, $headline, $context, 75);
            }
            $second = $this->second($movers, $headline);
            if ($second !== null) {
                $sentences[] = $this->moveSentence('second', 70, $payload, $second, $previousMonth);
                if ($second['good'] === false && $second['tier'] <= 2) {
                    $actions[] = $this->investigate($payload, $second, null, 65);
                }
            }
        }
        foreach ($payload['sources'] as $source) {
            if (($speed = $this->speed($payload, $source)) !== null) {
                $actions[] = $speed;
            }
        }
        foreach ($payload['contributions'] as $index => $item) {
            $params = $item['params'];
            $actions[] = $this->candidate((string) ($item['id'] ?? $item['key'].':'.$index), (int) $item['weight'], ['kind' => 'host', 'host_kind' => (string) $item['key']],
                title: __($item['title_key'], $params), detail: __($item['detail_key'], $params), evidence: __($item['evidence_key'], $params));
        }

        return ['sentences' => $this->unique($sentences), 'actions' => $this->unique($actions)];
    }

    /**
     * Failed or stopped collection and downtime in the month.
     *
     * @param  array<string, mixed>  $source
     * @param  list<array<string, mixed>>  $sentences
     * @param  list<array<string, mixed>>  $actions
     */
    private function health(array $source, string $month, array &$sentences, array &$actions): void
    {
        $health = $source['health'] ?? [];
        $name = (string) $source['label'];
        $id = (int) $source['id'];
        $monthName = $this->monthLabel($month, 'F');
        if ($health['failing'] ?? false) {
            $params = ['source' => $name, 'failed' => (int) $health['failed_runs'], 'runs' => (int) $health['runs'], 'month' => $monthName];
            $sentences[] = $this->candidate('failing:'.$id, 88, ['kind' => 'failing'], text: __('Collection for :source was failing at the end of the month (:failed of :runs runs failed).', $params));
            $actions[] = $this->candidate('reconnect:'.$id, 100, ['kind' => 'reconnect'], title: __('Reconnect :source', $params),
                detail: __('The latest collection failed. Check the connected account and the settings at the provider.'), evidence: __('Failed runs in :month: :failed', $params));
        }
        if (($source['paused_on'] ?? null) !== null) {
            $params = ['source' => $name, 'date' => $this->dayLabel($source['paused_on'])];
            $sentences[] = $this->candidate('paused:'.$id, 86, ['kind' => 'paused'], text: __(':source stopped collecting on :date, so later days are missing.', $params));
            $actions[] = $this->candidate('resume:'.$id, 90, ['kind' => 'resume'], title: __('Turn :source back on', $params),
                detail: __('Collection stopped on :date. Turn it on again if this data source should still report.', $params), evidence: __('Last data: :date', $params));
        }
        $minutes = $health['downtime_minutes'] ?? null;
        if ($minutes !== null && $minutes >= 10 && ($health['lowest_uptime_date'] ?? null) !== null) {
            $params = ['source' => $name, 'minutes' => Number::format(round($minutes), 0, locale: app()->getLocale()), 'days' => (int) $health['down_days'],
                'uptime' => Number::format((float) $health['lowest_uptime'], 2, locale: app()->getLocale()).'%', 'date' => $this->dayLabel($health['lowest_uptime_date'])];
            $sentences[] = $this->candidate('downtime:'.$id, 85, ['kind' => 'downtime', 'minutes' => round($minutes)], text: __(':source was down for about :minutes min in total (days affected: :days, lowest uptime :uptime on :date).', $params));
            $actions[] = $this->candidate('outage:'.$id, 80, ['kind' => 'outage', 'minutes' => round($minutes)], title: __('Find the cause of the downtime'),
                detail: __(':source was down for about :minutes min. Start with the monitor’s incidents around :date.', $params), evidence: __('Lowest uptime: :uptime', $params));
        }
    }

    /**
     * Every figure that moved against the month before, with its tier and whether the move is good.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function movers(array $payload): array
    {
        $movers = [];
        foreach ($payload['sources'] as $source) {
            foreach ($source['metrics'] as $figure) {
                if (in_array($figure['key'], self::NEVER_LEAD, true) || in_array($figure['key'], MonthlySnapshot::OMITTED, true) || $figure['unit'] === 'status') {
                    continue;
                }
                $comparison = $this->compare($figure, 'previous');
                if ($comparison === null) {
                    continue;
                }
                $tier = in_array($figure['key'], self::TIERS[1], true) ? 1 : (in_array($figure['key'], self::TIERS[2], true) ? 2 : 3);
                $mover = ['source' => $source, 'figure' => $figure, 'tier' => $tier, 'change' => $comparison['change'], 'per_day' => $comparison['per_day'], 'good' => $this->good($figure['key'], $comparison['change'])];
                if ($this->material($mover)) {
                    $movers[] = $mover;
                }
            }
        }

        return $movers;
    }

    /** @param  array<string, mixed>  $mover */
    private function material(array $mover): bool
    {
        $figure = $mover['figure'];
        $change = abs($mover['change']);
        $floor = match (true) {
            $figure['unit'] === 'currency', in_array($figure['key'], ['paddle_mrr', 'paddle_active_subscribers'], true) => 0.05,
            $mover['tier'] <= 2 => 0.10,
            default => 0.25,
        };
        if ($change < $floor) {
            return false;
        }
        if (in_array($figure['unit'], ['count', 'lines'], true)) {
            $volume = max(abs((float) $figure['value']), abs((float) $figure['previous']));

            return $volume >= ($mover['tier'] <= 2 ? 20 : 50);
        }
        if ($figure['unit'] === 'score') {
            return abs((float) $figure['value'] - (float) $figure['previous']) >= 10;
        }

        return true;
    }

    /**
     * The largest move in the most important tier that has one.
     *
     * @param  list<array<string, mixed>>  $movers
     * @return array<string, mixed>|null
     */
    private function leader(array $movers): ?array
    {
        $best = null;
        foreach ($movers as $mover) {
            if ($best === null || $mover['tier'] < $best['tier'] || ($mover['tier'] === $best['tier'] && abs($mover['change']) > abs($best['change']))) {
                $best = $mover;
            }
        }

        return $best;
    }

    /**
     * A second figure worth a sentence: an outcome or audience move in the other direction, else the next largest.
     *
     * @param  list<array<string, mixed>>  $movers
     * @param  array<string, mixed>  $headline
     * @return array<string, mixed>|null
     */
    private function second(array $movers, array $headline): ?array
    {
        $family = fn (string $key): string => collect(self::FAMILIES)->search(fn (array $keys): bool => in_array($key, $keys, true)) ?: $key;
        $others = array_values(array_filter($movers, fn (array $mover): bool => $mover['tier'] <= 2 && $family($mover['figure']['key']) !== $family($headline['figure']['key'])));
        usort($others, fn (array $a, array $b): int => [$a['tier'], abs($b['change'])] <=> [$b['tier'], abs($a['change'])]);
        foreach ($others as $mover) {
            if ($headline['good'] !== null && $mover['good'] !== null && $mover['good'] !== $headline['good']) {
                return $mover;
            }
        }

        return $others[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $mover
     * @return array<string, mixed>
     */
    private function moveSentence(string $prefix, int $weight, array $payload, array $mover, string $previousMonth): array
    {
        $figure = $mover['figure'];
        $params = [
            'subject' => $this->subject($payload, $mover['source'], $figure), 'percent' => $this->percent($mover['change']),
            'value' => $this->number($figure, $figure['value']), 'previous' => $this->number($figure, $figure['previous']),
            'previous_month' => $this->monthLabel($previousMonth, 'F'),
        ];
        $text = match (true) {
            $mover['change'] > 0 && $mover['per_day'] => __(':subject rose :percent per day, to :value (:previous in :previous_month).', $params),
            $mover['change'] > 0 => __(':subject rose :percent to :value (:previous in :previous_month).', $params),
            $mover['per_day'] => __(':subject fell :percent per day, to :value (:previous in :previous_month).', $params),
            default => __(':subject fell :percent to :value (:previous in :previous_month).', $params),
        };

        return $this->candidate($prefix.':'.$mover['source']['id'].':'.$figure['key'], $weight, ['kind' => $prefix] + $this->facts($mover), text: $text);
    }

    /**
     * Why the headline figure likely moved: last month was the exception, an event, one channel or page, the season,
     * or else where it stands against last year.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $mover
     * @return array{kind: string, sentence: array<string, mixed>, params: array<string, string>}|null
     */
    private function context(array $payload, array $mover, string $month, string $previousMonth): ?array
    {
        $figure = $mover['figure'];
        $id = $mover['source']['id'].':'.$figure['key'];
        $facts = $this->facts($mover);
        $baseline = $figure['baseline'] ?? null;
        if ($baseline !== null && (float) $baseline > 0 && $figure['aggregation'] !== 'period' && ! ($figure['partial'] ?? false) && ! ($figure['previous_partial'] ?? false)) {
            $before = (float) $figure['previous'] - $baseline;
            $now = (float) $figure['value'] - $baseline;
            // Last month stood out from the two before it, and this month went at least halfway back.
            if (abs($before) / $baseline >= 0.3 && abs($now) <= abs($before) * 0.5 && ($before > 0) !== ($mover['change'] > 0)) {
                $params = ['average' => $this->number($figure, $baseline), 'previous_month' => $this->monthLabel($previousMonth, 'F')];
                $event = $this->eventFor($this->events($payload, 'previous_events'), $mover['source']['id'], $figure['key'], $before > 0);
                $text = $event === null
                    ? __('The two months before averaged :average, so :previous_month was likely the exception.', $params)
                    : __('The two months before averaged :average, so :previous_month was likely the exception (“:event” on :date).', $params + ['event' => $event['title'], 'date' => $this->dayLabel($event['date'])]);

                return ['kind' => 'normal', 'sentence' => $this->candidate('normal:'.$id, 90, ['kind' => 'normal'] + $facts, text: $text), 'params' => $params];
            }
        }
        $event = $this->eventFor($this->events($payload), $mover['source']['id'], $figure['key'], $mover['change'] > 0);
        if ($event !== null) {
            $shift = $event['shift'];
            $params = ['event' => $event['title'], 'date' => $this->dayLabel($event['date']), 'before' => $this->number($figure, (float) $shift['before']),
                'after' => $this->number($figure, (float) $shift['after']), 'metric' => __(MetricRegistry::get($figure['key'])->label)];
            $text = __('That likely lines up with “:event” on :date: the daily average went from :before in the week before to :after in the week after.', $params);

            return ['kind' => 'event', 'sentence' => $this->candidate('event:'.$event['index'].':'.$id, 90, ['kind' => 'event'] + $facts, text: $text), 'params' => $params];
        }
        $driver = $this->driver($mover);
        if ($driver !== null) {
            $params = ['item' => $driver['label'], 'previous' => $this->count($driver['previous']), 'value' => $this->count($driver['value'])];

            return ['kind' => 'driver', 'sentence' => $this->candidate('driver:'.$id.':'.$driver['dimension'], 90, ['kind' => 'driver', 'dimension' => $driver['dimension']] + $facts,
                text: __('Most of the change likely came from :item (:previous → :value).', $params)), 'params' => $params];
        }
        $season = $this->compare(['value' => $figure['year_ago'] ?? null, 'previous' => $figure['year_ago_previous'] ?? null, 'partial' => $figure['year_partial'] ?? false,
            'previous_partial' => $figure['year_previous_partial'] ?? false, 'days_in_month' => $figure['year_days'] ?? $figure['days_in_month'], 'days_known' => $figure['year_known'] ?? null,
            'previous_days' => $figure['year_previous_days'] ?? null, 'previous_known' => $figure['year_previous_known'] ?? null] + $figure, 'previous');
        if ($season !== null && ($season['change'] > 0) === ($mover['change'] > 0) && abs($season['change']) >= abs($mover['change']) * 0.5) {
            $params = ['percent' => $this->percent($season['change'])];
            $text = $season['change'] > 0 ? __('The same months last year rose :percent too, so the change is likely seasonal.', $params)
                : __('The same months last year fell :percent too, so the change is likely seasonal.', $params);

            return ['kind' => 'season', 'sentence' => $this->candidate('season:'.$id, 90, ['kind' => 'season', 'last_year_change' => round($season['change'] * 100)] + $facts, text: $text), 'params' => $params];
        }
        $year = $this->compare($figure, 'year_ago');
        if ($year !== null) {
            $params = ['percent' => $this->percent($year['change']), 'month' => $this->monthLabel(CarbonImmutable::parse($month.'-01')->subYear()->format('Y-m')), 'value' => $this->number($figure, $figure['year_ago'])];
            $text = $year['change'] >= 0 ? __('That is :percent more than in :month (:value).', $params) : __('That is :percent less than in :month (:value).', $params);

            return ['kind' => 'year', 'sentence' => $this->candidate('year:'.$id, 75, ['kind' => 'year', 'year_change' => round($year['change'] * 100)] + $facts, text: $text), 'params' => $params];
        }

        return null;
    }

    /**
     * The one-off event whose week after moved this figure most in the given direction; notes before releases on a tie.
     *
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>|null The event with its index and the matching shift.
     */
    private function eventFor(array $events, int|string $sourceId, string $key, bool $up): ?array
    {
        $best = null;
        foreach ($events as $index => $event) {
            foreach ($event['shifts'] ?? [] as $shift) {
                if ((int) $shift['source_id'] !== (int) $sourceId || (string) $shift['key'] !== $key || ($shift['after'] > $shift['before']) !== $up) {
                    continue;
                }
                $size = abs($shift['after'] / $shift['before'] - 1);
                $rank = [$size, $event['origin'] === 'manual' ? 1 : 0];
                if ($best === null || $rank > $best['rank']) {
                    $best = $event + ['index' => $index, 'shift' => $shift, 'rank' => $rank];
                }
            }
        }

        return $best;
    }

    /**
     * A channel, referrer or landing page that moved the same way and carries much more of the change than its share
     * of traffic. Visits fall evenly across channels after a spike, and then no single channel is named.
     *
     * @param  array<string, mixed>  $mover
     * @return array{dimension: string, label: string, previous: float, value: float}|null
     */
    private function driver(array $mover): ?array
    {
        if (! in_array($mover['figure']['key'], ['visits', 'visitors', 'pageviews'], true)) {
            return null;
        }
        $source = $mover['source'];
        $lists = ['channel' => [$source['channels'] ?? [], $source['previous_channels'] ?? []]];
        foreach (['referrer', 'landing_page'] as $dimension) {
            $lists[$dimension] = [$this->items($source['breakdowns'] ?? [], $dimension), $this->items($source['previous_breakdowns'] ?? [], $dimension)];
        }
        foreach ($lists as $dimension => [$now, $before]) {
            $previous = [];
            foreach ($before as $row) {
                $previous[$row['channel']] = (float) $row['visits'];
            }
            $total = array_sum($previous);
            $current = array_sum(array_column($now, 'visits'));
            $change = $current - $total;
            if ($total <= 0 || abs($change) < 1 || ($change > 0) !== ($mover['change'] > 0)) {
                continue;
            }
            foreach ($now as $row) {
                if (! isset($previous[$row['channel']])) {
                    continue;
                }
                $delta = (float) $row['visits'] - $previous[$row['channel']];
                $explains = $delta / $change;
                $share = $previous[$row['channel']] / $total;
                if (($delta > 0) === ($change > 0) && $explains >= 0.3 && $explains >= $share * 1.5) {
                    $label = $dimension === 'channel' ? __(AudienceBreakdowns::CHANNEL_LABELS[$row['channel']] ?? $row['channel']) : $row['channel'];

                    return ['dimension' => $dimension, 'label' => (string) $label, 'previous' => $previous[$row['channel']], 'value' => (float) $row['visits']];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $breakdowns
     * @return list<array{channel: string, visits: float}>
     */
    private function items(array $breakdowns, string $dimension): array
    {
        foreach ($breakdowns as $report) {
            if ($report['dimension'] === $dimension) {
                $items = [];
                foreach ($report['items'] as $item) {
                    if (($value = $item['visits'] ?? $item['visitors']) !== null) {
                        $items[] = ['channel' => (string) $item['item'], 'visits' => (float) $value];
                    }
                }

                return $items;
            }
        }

        return [];
    }

    /**
     * An action for a move in the wrong direction, specific when the context or the search queries say where to look.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $mover
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    private function investigate(array $payload, array $mover, ?array $context, int $weight): array
    {
        $figure = $mover['figure'];
        $metric = $this->subject($payload, $mover['source'], $figure);
        $evidence = __(':metric: :previous → :value', ['metric' => $metric, 'previous' => $this->number($figure, $figure['previous']), 'value' => $this->number($figure, $figure['value'])]);
        $id = 'investigate:'.$mover['source']['id'].':'.$figure['key'];
        $facts = ['kind' => 'investigate'] + $this->facts($mover);
        if (($context['kind'] ?? null) === 'event') {
            $params = array_replace($context['params'], ['metric' => $metric]);

            return $this->candidate($id, $weight, $facts + ['context' => 'event'], title: __('Check whether “:event” changed :metric', $params),
                detail: __('The daily average went from :before in the week before :date to :after in the week after.', $params), evidence: $evidence);
        }
        if (($context['kind'] ?? null) === 'driver') {
            $params = array_replace($context['params'], ['metric' => $metric]);

            return $this->candidate($id, $weight, $facts + ['context' => 'driver'], title: __('Look into :item', $params),
                detail: __(':item went from :previous to :value. It likely explains most of the change in :metric.', $params), evidence: $evidence);
        }
        if ($figure['key'] === 'clicks') {
            foreach ($payload['query_losses'] ?? [] as $loss) {
                if ((int) $loss['source_id'] === (int) $mover['source']['id']) {
                    $params = ['query' => $loss['query'], 'lost' => $this->count($loss['previous_clicks'] - $loss['clicks']), 'previous' => $this->count($loss['previous_clicks']), 'value' => $this->count($loss['clicks'])];

                    return $this->candidate($id, $weight, $facts + ['context' => 'query'], title: __('Check the query “:query”', $params),
                        detail: __('It lost :lost clicks (:previous → :value). Look at its position and the page that ranks for it.', $params), evidence: $evidence);
                }
            }
        }
        $params = ['metric' => $metric, 'percent' => $this->percent($mover['change'])];

        return $this->candidate($id, $weight, $facts, title: __('Look into the drop in :metric', $params),
            detail: __(':metric fell :percent. Explore the month to see which days and sources fell.', $params), evidence: $evidence);
    }

    /**
     * A page speed source whose lab score fell by 10 points or more, or whose lab LCP or blocking time rose by a quarter.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>|null
     */
    private function speed(array $payload, array $source): ?array
    {
        foreach (['lab_score', 'lab_lcp', 'lab_tbt'] as $key) {
            $figure = null;
            foreach ($source['metrics'] as $candidate) {
                if ($candidate['key'] === $key) {
                    $figure = $candidate;
                }
            }
            if ($figure === null || $figure['value'] === null || $figure['previous'] === null) {
                continue;
            }
            $worse = $key === 'lab_score' ? (float) $figure['previous'] - (float) $figure['value'] >= 10
                : (float) $figure['previous'] > 0 && (float) $figure['value'] / (float) $figure['previous'] - 1 >= 0.25 && ProjectLayout::quality($key, $figure['value']) !== 'good';
            if (! $worse) {
                continue;
            }
            $params = ['page' => (string) $source['label'], 'metric' => __(MetricRegistry::get($key)->label), 'previous' => $this->number($figure, $figure['previous']), 'value' => $this->number($figure, $figure['value'])];
            $detail = __(':metric went from :previous to :value.', $params);
            $event = $this->eventFor($this->events($payload), $source['id'], $key, $key !== 'lab_score');
            if ($event !== null) {
                $detail = __(':metric went from :previous to :value. “:event” on :date likely lines up with it.', $params + ['event' => $event['title'], 'date' => $this->dayLabel($event['date'])]);
            }

            return $this->candidate('speed:'.$source['id'], 60, ['kind' => 'speed', 'metric' => $key, 'change' => (float) $figure['previous'] === 0.0 ? null : round(((float) $figure['value'] / (float) $figure['previous'] - 1) * 100)],
                title: __('Check what slowed :page', $params), detail: $detail, evidence: __(':metric: :previous → :value', $params));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $mover
     * @return array<string, mixed>
     */
    private function facts(array $mover): array
    {
        return ['metric' => $mover['figure']['key'], 'tier' => $mover['tier'], 'change' => round($mover['change'] * 100), 'good' => $mover['good']];
    }

    /**
     * @param  array<string, mixed>  $facts
     * @return array<string, mixed>
     */
    private function candidate(string $id, int $weight, array $facts, string $text = '', string $title = '', string $detail = '', string $evidence = ''): array
    {
        return ['id' => $id, 'weight' => $weight, 'facts' => $facts, 'text' => $text, 'title' => $title, 'detail' => $detail, 'evidence' => $evidence];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<array<string, mixed>>
     */
    private function unique(array $candidates): array
    {
        $out = [];
        foreach ($candidates as $candidate) {
            $out[$candidate['id']] ??= $candidate;
        }

        return array_values($out);
    }

    /**
     * The host's order when every id it names exists; health candidates are kept either way.
     *
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<string>|null  $ranking
     * @return list<array<string, mixed>>
     */
    private function pick(array $candidates, int $limit, ?array $ranking): array
    {
        $byId = [];
        foreach ($candidates as $candidate) {
            $byId[$candidate['id']] = $candidate;
        }
        $ordered = $candidates;
        usort($ordered, fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);
        if ($ranking !== null && $ranking !== [] && array_diff($ranking, array_keys($byId)) === []) {
            $chosen = array_map(fn (string $id): array => $byId[$id], array_values(array_unique($ranking)));
            foreach ($ordered as $candidate) {
                if (in_array($candidate['facts']['kind'] ?? null, self::MANDATORY, true) && ! in_array($candidate['id'], $ranking, true)) {
                    array_unshift($chosen, $candidate);
                }
            }
            $ordered = $chosen;
        }

        return array_slice($ordered, 0, $limit);
    }

    private function count(float $value): string
    {
        return Number::format(round($value), 0, locale: app()->getLocale());
    }
}
