<?php

use App\Domain\ReferralService;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\FormSchema;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Security\BlindIndex;
use App\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function facility(array $a = []): Facility
{
    static $n = 0;
    $n++;

    return Facility::query()->create($a + [
        'name' => "Test Facility $n", 'type' => 'health_centre', 'county' => 'Synthetic County', 'is_active' => true,
        'phone' => '+25470000'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'info_verified_at' => now(), 'mfl_code' => (string) (800000 + $n),
    ]);
}

function user(Role $role, ?Facility $f = null, array $a = []): User
{
    static $n = 0;
    $n++;
    $f ??= $role->needsFacility() ? facility() : null;

    return User::query()->create($a + [
        'name' => ucfirst($role->value)." User $n", 'email' => "u$n.".$role->value.'@test.invalid', 'password' => 'Correct-Horse-1234',
        'role' => $role, 'facility_id' => $f?->id, 'status' => 'active', 'mfa_secret' => Totp::generateSecret(), 'mfa_enabled_at' => now(),
    ]);
}

/** Authenticated, MFA-verified, freshly re-authenticated session. */
function signedIn(TestCase $t, User $u): TestCase
{
    return $t->actingAs($u)->withSession(['mfa_ok' => true, 'reauth_at' => time(), 'last_activity' => time(), 'session_started' => time()]);
}

function patientFor(User $u, array $a = []): Patient
{
    static $n = 0;
    $n++;
    $name = $a['full_name'] ?? "Synthetic Person $n";
    $dob = $a['date_of_birth'] ?? '1990-05-05';
    $idv = $a['identifier'] ?? "SYN-$n";

    return Patient::query()->withoutGlobalScopes()->create([
        'facility_id' => $u->facility_id, 'full_name' => $name, 'date_of_birth' => $dob, 'sex' => $a['sex'] ?? 'female',
        'identifier_type' => 'facility_record', 'identifier' => $idv, 'identifier_hash' => BlindIndex::make('identifier:facility_record', $idv),
        'name_dob_hash' => BlindIndex::nameDob($name, $dob, $a['sex'] ?? 'female'), 'is_synthetic' => true, 'created_by' => $u->id,
    ]);
}

function activeSchema(): FormSchema
{
    return FormSchema::query()->firstOrCreate(['key' => 'generic-referral', 'version' => 1], [
        'name' => 'Generic', 'status' => 'active', 'fields' => Database\Seeders\BaselineSeeder::FIELDS, 'activated_at' => now(),
    ]);
}

/** A draft referral created through the real service as $from. */
function draftReferral(User $from, Facility $to, array $a = []): Referral
{
    test()->be($from);
    $p = $a['patient'] ?? patientFor($from);

    return ReferralService::create($from, $p, $a + [
        'receiving_facility_id' => $to->id, 'priority' => 'urgent', 'reason_code' => 'medical', 'reason_text' => 'Synthetic reason text',
        'include_note' => true, 'clinical_note' => 'Synthetic clinical note', 'form_schema_id' => activeSchema()->id, 'form_data' => ['treatment_given' => 'Synthetic treatment'],
    ]);
}

function sentReferral(User $from, Facility $to, array $a = []): Referral
{
    $r = draftReferral($from, $to, $a);
    test()->be($from);

    return ReferralService::send($from, $r, ['confirmed' => true, 'basis' => 'written']);
}

/** Sent and accepted by the receiving coordinator. */
function acceptedReferral(User $from, User $coord, array $a = []): Referral
{
    $r = sentReferral($from, $coord->facility, $a);
    test()->be($coord);

    return ReferralService::accept($coord, $r);
}
