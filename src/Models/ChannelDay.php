<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/** Visits of one acquisition channel on one reporting date. A date's channels are replaced together. */
class ChannelDay extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'visits' => 'float', 'is_partial' => 'boolean', 'collected_at' => 'immutable_datetime'];
    }
}
