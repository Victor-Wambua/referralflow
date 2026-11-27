<?php

namespace App\Domain;

use App\Models\Patient;
use App\Models\User;
use App\Security\AuditLog;
use App\Security\BlindIndex;
use Illuminate\Validation\ValidationException;

final class PatientService
{
    /** @return array{strong:?Patient,probable:?Patient} matches within the user's own facility only */
    public static function matches(array $d): array
    {
        $strong = null;
        if (! empty($d['identifier']) && ! empty($d['identifier_type'])) {
            $strong = Patient::query()->where('identifier_hash', self::idHash($d['identifier_type'], $d['identifier']))->first();
        }
        $probable = Patient::query()
            ->where('name_dob_hash', BlindIndex::nameDob($d['full_name'], $d['date_of_birth'], $d['sex']))
            ->when($strong, fn ($q) => $q->where('id', '!=', $strong->id))
            ->first();

        return ['strong' => $strong, 'probable' => $probable];
    }

    public static function idHash(string $type, string $value): string
    {
        return BlindIndex::make('identifier:'.$type, $value);
    }

    public static function create(User $u, array $d): Patient
    {
        if (empty($d['synthetic_confirm'])) {
            throw ValidationException::withMessages(['synthetic_confirm' => 'Confirm this is a synthetic (not real) person.']);
        }
        $p = Patient::query()->create([
            'facility_id' => $u->facility_id,
            'full_name' => trim($d['full_name']),
            'date_of_birth' => $d['date_of_birth'],
            'sex' => $d['sex'],
            'identifier_type' => $d['identifier_type'] ?? null,
            'identifier' => $d['identifier'] ?? null,
            'identifier_hash' => ! empty($d['identifier']) && ! empty($d['identifier_type']) ? self::idHash($d['identifier_type'], $d['identifier']) : null,
            'name_dob_hash' => BlindIndex::nameDob($d['full_name'], $d['date_of_birth'], $d['sex']),
            'is_synthetic' => true,
            'created_by' => $u->id,
        ]);
        AuditLog::record('patient.create', 'patient', $p->id);

        return $p;
    }
}
