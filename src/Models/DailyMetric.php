<?php

namespace Metribay\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class DailyMetric extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'float', 'is_partial' => 'boolean', 'date' => 'immutable_date', 'collected_at' => 'immutable_datetime', 'revised_at' => 'immutable_datetime', 'observed_at' => 'immutable_datetime', 'window_start' => 'immutable_datetime', 'window_end' => 'immutable_datetime'];
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = CarbonImmutable::parse($value)->toDateString();
    }
}
