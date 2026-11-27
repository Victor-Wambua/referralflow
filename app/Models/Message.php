<?php

namespace App\Models;

use App\Models\Concerns\ScopedByReferral;
use Illuminate\Database\Eloquent\Model;


class Message extends Model
{
    use ScopedByReferral;


    protected $guarded = [];



    protected function casts(): array
    {
        return [
            'body' => 'encrypted',



        ];
    }


    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class, 'author_facility_id');
    }
}
