<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Attachment extends Model
{
    use ScopedByReferral;
    use HasUlids;

    protected $guarded = [];



    protected function casts(): array
    {
        return [
            'original_name' => 'encrypted',



        ];
    }


}
