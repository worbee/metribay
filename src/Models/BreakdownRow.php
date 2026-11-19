<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * One item of an analytics breakdown (a referrer, landing page, campaign, country, device or goal) for one reporting
 * window (7, 30 or 90 days, or the window before it). Rows of a dimension are replaced on every collection.
 */
class BreakdownRow extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['days' => 'integer', 'previous' => 'boolean', 'visits' => 'float', 'visitors' => 'float', 'conversions' => 'float', 'is_partial' => 'boolean',
            'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'collected_at' => 'immutable_datetime'];
    }
}
