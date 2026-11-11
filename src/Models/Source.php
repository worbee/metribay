<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class Source extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'config' => 'array', 'enabled' => 'boolean', 'last_success_at' => 'immutable_datetime', 'last_error_at' => 'immutable_datetime', 'last_started_at' => 'immutable_datetime', 'sitemap_baselined_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
