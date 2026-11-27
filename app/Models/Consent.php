<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Model;

class Consent extends Model
{
    use ScopedByReferral;

    protected $guarded = [];

    protected $table = 'consents';

    /** @var array<string, string> */
    protected $casts = [
        'witness' => 'encrypted',

        'recorded_at' => 'datetime', 'withdrawn_at' => 'datetime', 'includes_clinical_note' => 'boolean',

    ];
}
