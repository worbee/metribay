<?php

namespace Metribay\Core\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class Annotation extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['date' => 'immutable_date', 'occurred_at' => 'immutable_datetime'];
    }

    public function setDateAttribute(mixed $value): void
    {
        $this->attributes['date'] = CarbonImmutable::parse($value)->toDateString();
    }
}
