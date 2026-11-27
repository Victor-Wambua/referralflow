<?php

use App\Domain\FormSchemaValidator;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\Facility;
use App\Models\FormSchema;
use App\Models\Referral;
use App\Models\User;
use App\Security\Totp;
use Database\Seeders\BaselineSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->A = facility(['name' => 'Alpha']);
    $this->B = facility(['name' => 'Beta']);
    $this->adminA = user(Role::FacilityAdmin, $this->A);
    $this->platform = user(Role::PlatformAdmin);
});

it('lets a facility admin invite clinicians and coordinators into their own facility only', function () {
    $res = signedIn($this, $this->adminA)->post('/admin/users', ['name' => 'New Nurse', 'email' => 'new.nurse@test.invalid', 'role' => 'clinician']);
    $res->assertSessionHasNoErrors()->assertSessionHas('temp_password');
    $pw = session('temp_password');
    $u = User::query()->where('email', 'new.nurse@test.invalid')->first();
    expect($u->facility_id)->toBe($this->A->id)->and($u->must_change_password)->toBeTrue()->and($u->mfa_enabled_at)->toBeNull()->and($u->password)->toStartWith('$argon2id$');
    signedIn($this, $this->adminA)->post('/admin/users', ['name' => 'X', 'email' => 'x@test.invalid', 'role' => 'platform_admin'])->assertSessionHasErrors('role');
    signedIn($this, $this->adminA)->post('/admin/users', ['name' => 'X', 'email' => 'x@test.invalid', 'role' => 'clinician', 'facility_id' => $this->B->id])->assertSessionHasErrors('facility_id');
    signedIn($this, $this->adminA)->post('/admin/users', ['name' => 'Dup', 'email' => 'new.nurse@test.invalid', 'role' => 'clinician'])->assertSessionHasErrors('email');
    signedIn($this, $this->adminA)->post('/admin/users', ['name' => 'Bad', 'email' => 'not-an-email', 'role' => 'clinician'])->assertSessionHasErrors('email');
    expect(AuditEntry::query()->where('action', 'user.invite')->count())->toBe(1);
    // the temporary password works once, forces a change, and then MFA enrolment
    auth()->logout();
    $this->post('/login', ['email' => 'new.nurse@test.invalid', 'password' => $pw])->assertRedirect('/mfa');
    $this->get('/mfa')->assertSee('Set up two-step sign-in');
});

it('lets a platform admin create analysts and admins without a facility, and requires a facility for facility roles', function () {
    signedIn($this, $this->platform)->post('/admin/users', ['name' => 'An Alyst', 'email' => 'an@test.invalid', 'role' => 'network_analyst'])->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'an@test.invalid')->first()->facility_id)->toBeNull();
    signedIn($this, $this->platform)->post('/admin/users', ['name' => 'No Fac', 'email' => 'nf@test.invalid', 'role' => 'coordinator'])->assertSessionHasErrors('facility_id');
    signedIn($this, $this->platform)->post('/admin/users', ['name' => 'Has Fac', 'email' => 'hf@test.invalid', 'role' => 'coordinator', 'facility_id' => $this->B->id])->assertSessionHasNoErrors();
    expect(User::query()->where('email', 'hf@test.invalid')->first()->facility_id)->toBe($this->B->id);
});

it('scopes user management: no cross-facility changes, no self changes, suspension ends sessions, MFA reset forces re-enrolment', function () {
    $mine = user(Role::Clinician, $this->A);
    $theirs = user(Role::Clinician, $this->B);
    signedIn($this, $this->adminA)->post("/admin/users/{$theirs->id}/suspend")->assertForbidden();
    signedIn($this, $this->adminA)->post("/admin/users/{$theirs->id}", ['role' => 'coordinator'])->assertForbidden();
    signedIn($this, $this->adminA)->post("/admin/users/{$this->adminA->id}/suspend")->assertForbidden();
    signedIn($this, $this->adminA)->post("/admin/users/{$this->adminA->id}", ['role' => 'clinician'])->assertForbidden();
    // change role within the facility
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}", ['role' => 'coordinator'])->assertSessionHasNoErrors();
    expect($mine->fresh()->role)->toBe(Role::Coordinator);
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}", ['role' => 'platform_admin'])->assertSessionHasErrors('role');
    // suspend: sessions deleted and login refused
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $mine->id, 'payload' => 'x', 'last_activity' => time()]);
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}/suspend")->assertSessionHasNoErrors();
    expect($mine->fresh()->status)->toBe('suspended')->and(DB::table('sessions')->where('user_id', $mine->id)->count())->toBe(0);
    auth()->logout();
    $this->post('/login', ['email' => $mine->email, 'password' => 'Correct-Horse-1234'])->assertSessionHasErrors('email');
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}/suspend");
    expect($mine->fresh()->status)->toBe('active');
    // MFA reset
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}/mfa-reset")->assertSessionHasNoErrors();
    $mine->refresh();
    expect($mine->mfa_secret)->toBeNull()->and($mine->mfa_enabled_at)->toBeNull();
    auth()->logout();
    $this->post('/login', ['email' => $mine->email, 'password' => 'Correct-Horse-1234'])->assertRedirect('/mfa');
    $this->get('/mfa')->assertSee('Set up two-step sign-in');
    // password reset
    signedIn($this, $this->adminA)->post("/admin/users/{$mine->id}/password-reset")->assertSessionHas('temp_password');
    expect($mine->fresh()->must_change_password)->toBeTrue();
    expect(AuditEntry::query()->whereIn('action', ['user.role_change', 'user.suspend', 'user.reactivate', 'user.mfa_reset', 'user.password_reset'])->count())->toBe(5);
});

it('registers facilities (unverified) with validation, and edits them', function () {
    $ok = ['name' => 'New Dispensary', 'type' => 'dispensary', 'county' => 'County Z', 'mfl_code' => '123456', 'phone' => '+254700000000'];
    signedIn($this, $this->platform)->post('/admin/facilities', $ok)->assertSessionHasNoErrors();
    $f = Facility::query()->where('mfl_code', '123456')->first();
    expect($f->verificationState())->toBe('unverified')->and($f->data_origin)->toBe('user-submitted')->and($f->is_active)->toBeTrue();
    signedIn($this, $this->platform)->post('/admin/facilities', ['mfl_code' => 'abc'] + $ok)->assertSessionHasErrors('mfl_code');
    signedIn($this, $this->platform)->post('/admin/facilities', ['mfl_code' => '123456'] + $ok)->assertSessionHasErrors('mfl_code'); // duplicate
    signedIn($this, $this->platform)->post('/admin/facilities', ['type' => 'palace'] + $ok)->assertSessionHasErrors('type');
    signedIn($this, $this->platform)->post('/admin/facilities', ['name' => ''] + $ok)->assertSessionHasErrors('name');
    signedIn($this, $this->platform)->post("/admin/facilities/{$f->id}", ['name' => 'Renamed', 'type' => 'clinic', 'county' => 'County Z', 'is_active' => '0'])->assertSessionHasNoErrors();
    expect($f->fresh()->name)->toBe('Renamed')->and($f->fresh()->is_active)->toBeFalse();
    // an inactive facility can no longer be a destination
    $clin = user(Role::Clinician, $this->A);
    activeSchema();
    expect(fn () => draftReferral($clin, $f->fresh()))->toThrow(ValidationException::class);
});

it('versions the referral form: definitions are validated, activation retires the old version, old referrals keep theirs', function () {
    $v1 = activeSchema();
    expect(FormSchemaValidator::definitionErrors([]))->not->toBe([])
        ->and(FormSchemaValidator::definitionErrors([['key' => 'Bad Key', 'label' => 'x', 'type' => 'text']]))->not->toBe([])
        ->and(FormSchemaValidator::definitionErrors([['key' => 'a', 'label' => 'x', 'type' => 'magic']]))->not->toBe([])
        ->and(FormSchemaValidator::definitionErrors([['key' => 'a', 'label' => 'x', 'type' => 'select']]))->not->toBe([])
        ->and(FormSchemaValidator::definitionErrors([['key' => 'a', 'label' => 'x', 'type' => 'text'], ['key' => 'a', 'label' => 'y', 'type' => 'text']]))->not->toBe([])
        ->and(FormSchemaValidator::definitionErrors(BaselineSeeder::FIELDS))->toBe([]);

    $from = user(Role::Clinician, $this->A);
    $old = draftReferral($from, $this->B);
    $fields = json_encode([['key' => 'weight_kg', 'label' => 'Weight (kg)', 'type' => 'number', 'required' => true]]);
    signedIn($this, $this->platform)->post('/admin/forms', ['key' => 'generic-referral', 'name' => 'v2', 'fields' => '{not json'])->assertSessionHasErrors('fields');
    signedIn($this, $this->platform)->post('/admin/forms', ['key' => 'generic-referral', 'name' => 'v2', 'fields' => '[]'])->assertSessionHasErrors('fields');
    signedIn($this, $this->platform)->post('/admin/forms', ['key' => 'generic-referral', 'name' => 'v2', 'fields' => $fields])->assertSessionHasNoErrors();
    $v2 = FormSchema::query()->where('version', 2)->first();
    expect($v2->status)->toBe('draft')->and(FormSchema::active()->id)->toBe($v1->id);
    signedIn($this, $this->platform)->post("/admin/forms/{$v2->id}/activate")->assertSessionHasNoErrors();
    expect(FormSchema::active()->id)->toBe($v2->id)->and($v1->fresh()->status)->toBe('retired');

    // the wizard now asks for the new field and enforces it
    $payload = ['patient_mode' => 'new', 'full_name' => 'F S', 'date_of_birth' => '1990-01-01', 'sex' => 'male', 'synthetic_confirm' => '1', 'priority' => 'routine', 'reason_code' => 'other', 'reason_text' => 'r', 'receiving_facility_id' => $this->B->id, 'action' => 'draft'];
    signedIn($this, $from)->post('/referrals', $payload)->assertSessionHasErrors('form.weight_kg');
    signedIn($this, $from)->post('/referrals', $payload + ['form' => ['weight_kg' => 'heavy']])->assertSessionHasErrors('form.weight_kg');
    signedIn($this, $from)->post('/referrals', $payload + ['form' => ['weight_kg' => '12.5']])->assertSessionHasNoErrors();
    expect(Referral::query()->where('form_schema_id', $v2->id)->count())->toBe(1);
    // the older referral still points at v1 and still renders
    expect($old->fresh()->form_schema_id)->toBe($v1->id);
    signedIn($this, $from)->get("/referrals/{$old->id}")->assertOk()->assertSee('Synthetic treatment');
});

it('keeps the access log scoped by role', function () {
    $clin = user(Role::Clinician, $this->A);
    $coord = user(Role::Coordinator, $this->A);
    $other = user(Role::Clinician, $this->B);
    signedIn($this, $clin)->get('/referrals');
    signedIn($this, $other)->get('/referrals');
    $res = signedIn($this, $clin)->get('/access')->assertOk();
    expect($res->getContent())->toContain('referral.list')->not->toContain('>'.$other->id.' (clinician)<');
    // coordinator sees the facility's entries, never another facility's
    $html = signedIn($this, $coord)->get('/access')->assertOk()->getContent();
    expect($html)->toContain('('.'clinician'.')');
    expect(preg_match_all('/<td>'.$other->id.' \(clinician\)<\/td>/', $html))->toBe(0);
    signedIn($this, $this->platform)->get('/admin/audit?action=referral.list')->assertOk()->assertSee('referral.list');
});

it('creates a working TOTP enrolment secret that is not stored until confirmed', function () {
    $u = user(Role::Clinician, null, ['mfa_secret' => null, 'mfa_enabled_at' => null]);
    $this->post('/login', ['email' => $u->email, 'password' => 'Correct-Horse-1234']);
    $this->get('/mfa');
    $pending = session('mfa_pending_secret');
    expect($u->fresh()->mfa_secret)->toBeNull()->and($pending)->toMatch('/^[A-Z2-7]{32}$/');
    expect(Totp::uri($pending, $u->email))->toStartWith('otpauth://totp/ReferralFlow:');
});
