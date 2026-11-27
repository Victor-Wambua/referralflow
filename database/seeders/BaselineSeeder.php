<?php

namespace Database\Seeders;

use App\Models\FormSchema;
use Illuminate\Database\Seeder;

class BaselineSeeder extends Seeder
{
    /** Generic, configurable referral form (NOT any official Kenyan form: see docs/DECISIONS.md D1). */
    public const FIELDS = [
        ['key' => 'treatment_given', 'label' => 'Treatment given so far', 'type' => 'textarea', 'required' => false, 'max' => 500],
        ['key' => 'current_medication', 'label' => 'Current medication', 'type' => 'text', 'required' => false, 'max' => 200],
        ['key' => 'allergies', 'label' => 'Known allergies', 'type' => 'select', 'required' => false, 'options' => [['value' => 'none', 'label' => 'None known'], ['value' => 'yes', 'label' => 'Yes (state in note)'], ['value' => 'unknown', 'label' => 'Not known']]],
        ['key' => 'transport_needed', 'label' => 'Transport needed', 'type' => 'boolean', 'required' => false],
        ['key' => 'accompanied_by', 'label' => 'Accompanied by', 'type' => 'select', 'required' => false, 'options' => [['value' => 'nobody', 'label' => 'Nobody'], ['value' => 'health_worker', 'label' => 'Health worker'], ['value' => 'family', 'label' => 'Family member']]],
    ];

    public function run(): void
    {
        if (! FormSchema::query()->where('key', 'generic-referral')->exists()) {
            FormSchema::query()->create(['key' => 'generic-referral', 'version' => 1, 'name' => 'Generic referral form (configurable, not an official form)', 'status' => 'active', 'fields' => self::FIELDS, 'activated_at' => now()]);
        }
    }
}
