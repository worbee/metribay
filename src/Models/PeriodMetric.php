<?php

namespace Metribay\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class PeriodMetric extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'float', 'is_partial' => 'boolean', 'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'collected_at' => 'immutable_datetime'];
    }

    public function setStartDateAttribute(mixed $value): void
    {
        $this->attributes['start_date'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function setEndDateAttribute(mixed $value): void
    {
        $this->attributes['end_date'] = CarbonImmutable::parse($value)->toDateString();
    }
}
