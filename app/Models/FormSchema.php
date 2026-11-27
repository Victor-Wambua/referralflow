<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSchema extends Model
{
    protected $table = 'referral_form_schemas';
    protected $guarded = [];

    protected function casts(): array
    {
        return ['fields' => 'array', 'activated_at' => 'datetime'];
    }

    public static function active(): ?self
    {
        return static::query()->where('status', 'active')->orderByDesc('version')->first();
    }
}
