<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

/**
 * One data source metric chart hidden from a project's page for everyone in the workspace.
 * Hiding only affects the project page; the overview and stored metrics are unchanged.
 */
class HiddenChart extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }
}
