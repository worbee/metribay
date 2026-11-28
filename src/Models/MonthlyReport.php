<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * A project's report for one calendar month. Once sealed_at is set the payload is never replaced.
 *
 * @property array<string, mixed> $payload
 */
class MonthlyReport extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'payload' => 'array', 'has_data' => 'boolean', 'sealed_at' => 'immutable_datetime'];
    }
}
