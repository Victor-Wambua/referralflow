<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalHold extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = ['placed_at' => 'datetime', 'released_at' => 'datetime'];
}
