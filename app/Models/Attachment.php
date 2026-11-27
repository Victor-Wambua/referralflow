<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasUlids;
    use ScopedByReferral;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'original_name' => 'encrypted',

    ];
}
