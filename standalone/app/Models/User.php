<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends \Metribay\Core\Models\User
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
}
