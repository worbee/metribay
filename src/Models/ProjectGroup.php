<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Metribay\Core\Models\Concerns\BelongsToWorkspace;

class ProjectGroup extends Model
{
    use BelongsToWorkspace;

    protected $guarded = [];
}
