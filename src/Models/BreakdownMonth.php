<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/** One archived calendar month of a breakdown dimension. Written once and kept. */
class BreakdownMonth extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'item_count' => 'integer', 'is_partial' => 'boolean', 'collected_at' => 'immutable_datetime'];
    }

    /** @return HasMany<BreakdownMonthRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(BreakdownMonthRow::class);
    }
}
