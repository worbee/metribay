<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class Project extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['version' => 'integer', 'archived' => 'boolean'];
    }

    /** @return HasMany<Source, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }

    /** @return BelongsTo<ProjectGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProjectGroup::class, 'group_id');
    }
}
