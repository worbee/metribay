<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class SyncRun extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }
}
