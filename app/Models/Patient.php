<?php

namespace App\Models;

use App\Security\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'full_name' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'identifier' => 'encrypted',
            'is_synthetic' => 'boolean',
            'anonymised_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('facility', function (Builder $q) {
            if (TenantContext::isSystem()) {
                return;
            }
            $u = TenantContext::user();
            if ($u && $u->isClinical() && $u->facility_id) {
                $q->where($q->getModel()->getTable().'.facility_id', $u->facility_id);
            } else {
                $q->whereRaw('1 = 0');
            }
        });
    }

    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    public function ageYears(?\DateTimeInterface $on = null): ?int
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return (int) \Illuminate\Support\Carbon::parse($this->date_of_birth)->diffInYears($on ?? now());
    }
}
