<?php

namespace Metribay\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * A dated note on a project's charts. Custom events are entered by people and may repeat;
 * automatic events (for example GitHub releases) are reconciled by collectors and never repeat.
 */
class ProjectEvent extends Model
{
    use BelongsToWorkspace;

    /** Predefined custom event colors. The first is the default. */
    public const COLORS = ['blue', 'green', 'amber', 'violet'];

    public const RECURRENCES = ['weekly', 'monthly', 'yearly'];

    /** Upper bound on generated occurrences, so an unbounded series cannot exhaust a request. */
    private const MAX_OCCURRENCES = 1000;

    protected $table = 'project_events';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'occurred_at' => 'immutable_datetime', 'recurrence_until' => 'immutable_date'];
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function isCustom(): bool
    {
        return $this->origin === 'manual';
    }

    /** Chart and list color: a custom color key, or "automatic" for provider-fetched events. */
    public function displayColor(): string
    {
        if (! $this->isCustom()) {
            return 'automatic';
        }

        return in_array($this->color, self::COLORS, true) ? $this->color : self::COLORS[0];
    }

    /** @return list<string> Occurrence dates (Y-m-d) between both dates, inclusive. */
    public function occurrencesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $first = $this->date->startOfDay();
        $from = $from->startOfDay();
        $to = $to->startOfDay();
        if (! $this->isCustom() || ! in_array($this->recurrence, self::RECURRENCES, true)) {
            return $first->betweenIncluded($from, $to) ? [$first->toDateString()] : [];
        }
        $until = $this->recurrence_until?->startOfDay();
        $last = $until && $until->lt($to) ? $until : $to;
        $dates = [];
        // Skip occurrences that end before the window; diffs are signed, so clamp at the first date.
        $skip = $from->gt($first) ? max(0, (int) floor(match ($this->recurrence) {
            'weekly' => $first->diffInDays($from) / 7,
            'monthly' => $first->diffInMonths($from),
            'yearly' => $first->diffInYears($from),
        }) - 1) : 0;
        for ($n = $skip; $n < $skip + self::MAX_OCCURRENCES; $n++) {
            // Step from the first date each time, so monthly events on the 31st do not drift.
            $date = match ($this->recurrence) {
                'weekly' => $first->addWeeks($n),
                'monthly' => $first->addMonthsNoOverflow($n),
                'yearly' => $first->addYearsNoOverflow($n),
            };
            if ($date->gt($last)) {
                break;
            }
            if ($date->gte($from)) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }

    /** Next occurrence on or after the given date, if any. */
    public function nextOccurrence(CarbonImmutable $after): ?string
    {
        return $this->occurrencesBetween($after, $after->addYears(2))[0] ?? null;
    }
}
