<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotice extends Model
{
    protected $table = 'notifications';

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['read_at' => 'datetime'];
}
