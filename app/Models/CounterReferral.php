<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Model;


class CounterReferral extends Model
{
    use ScopedByReferral;


    protected $guarded = [];



    protected function casts(): array
    {
        return [
            'summary' => 'encrypted',
            'follow_up' => 'encrypted',


        ];
    }


}
