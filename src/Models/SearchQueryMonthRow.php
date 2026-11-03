<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;

/** One query of an archived month; read only through its SearchQueryMonth, which carries the workspace. */
class SearchQueryMonthRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['clicks' => 'float', 'impressions' => 'float', 'position' => 'float'];
    }
}
