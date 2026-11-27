<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Model;


class ReferralEvent extends Model
{
    use ScopedByReferral;


    protected $guarded = [];
    public $timestamps = false;


    protected function casts(): array
    {
        return [
            'note' => 'encrypted',


            'occurred_at' => 'datetime',
        ];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

}
