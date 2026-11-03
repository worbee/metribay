<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * One search query of a Search Console or Bing data source for one reporting window (7, 30 or 90 days, or the
 * window before it). Rows are a snapshot of the provider's top queries and are replaced on every collection.
 */
class SearchQuery extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['days' => 'integer', 'previous' => 'boolean', 'clicks' => 'float', 'impressions' => 'float', 'position' => 'float', 'is_partial' => 'boolean',
            'start_date' => 'immutable_date', 'end_date' => 'immutable_date', 'collected_at' => 'immutable_datetime'];
    }
}
