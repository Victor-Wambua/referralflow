<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'completed_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class)->withoutGlobalScopes();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
