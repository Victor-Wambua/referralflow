<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only. Application guards here; database triggers enforce it as well. */
class AuditEntry extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function metaArray(): array
    {
        return $this->meta ? (json_decode($this->meta, true) ?: []) : [];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit entries are append-only.'));
    }
}
