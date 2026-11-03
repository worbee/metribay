<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * One archived calendar month of a Search Console or Bing data source's top queries. Written once when the month's
 * data is final and never replaced. Bing reports weeks, so its months hold the weeks dated in that month.
 */
class SearchQueryMonth extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'weeks' => 'integer', 'query_count' => 'integer', 'collected_at' => 'immutable_datetime'];
    }

    /** @return HasMany<SearchQueryMonthRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(SearchQueryMonthRow::class);
    }
}
