<?php

namespace Metribay\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Workspace extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        $model = config('auth.providers.users.model');
        if (! is_string($model) || ! is_a($model, User::class, true)) {
            throw new \LogicException('The configured user model must extend the Metribay user model.');
        }

        return $this->belongsTo($model, 'owner_id');
    }
}
