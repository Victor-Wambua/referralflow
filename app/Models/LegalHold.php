<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalHold extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['placed_at' => 'datetime', 'released_at' => 'datetime'];
    }
}
