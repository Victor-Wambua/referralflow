<?php

use App\Domain\ReferralService;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Security\AuditLog;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->A = facility();
    $this->B = facility();
    $this->clin = user(Role::Clinician, $this->A);
    $this->coord = user(Role::Coordinator, $this->B);
    $this->admin = user(Role::PlatformAdmin);
});

function actions(): array
{
    return AuditEntry::query()->orderBy('seq')->pluck('action')->all();
}

it('records every read and write of patient data with actor, role and facility', function () {
    $before = AuditEntry::query()->count();
    $r = acceptedReferral($this->clin, $this->coord);
    $c = $this->clin;
    $co = $this->coord;
    $id = $r->id;
    $steps = [
        ['referral.list', fn () => signedIn($this, $c)->get('/referrals')],
        ['referral.inbox', fn () => signedIn($this, $co)->get('/inbox')],
        ['referral.view', fn () => signedIn($this, $c)->get("/referrals/$id")],
        ['message.list', fn () => signedIn($this, $c)->get("/referrals/$id?tab=messages")],
        ['patient.reveal', fn () => signedIn($this, $c)->post("/referrals/$id/reveal", ['reason' => 'clinical_care'])],
        ['fhir.export', fn () => signedIn($this, $c)->get("/api/v1/referrals/$id/fhir")],
        ['patient.list', fn () => signedIn($this, $c)->get('/referrals/new')],
        ['message.post', fn () => signedIn($this, $c)->post("/referrals/$id/messages", ['body' => 'hello'])],
        ['referral.status', fn () => signedIn($this, $co)->post("/referrals/$id/schedule", ['appointment_at' => now()->addDay()->format('Y-m-d\TH:i')])],
    ];
    foreach ($steps as [$action, $do]) {
        $n = AuditEntry::query()->where('action', $action)->count();
        $do();
        expect(AuditEntry::query()->where('action', $action)->count())->toBeGreaterThan($n, "$action logged");
    }
    $e = AuditEntry::query()->where('action', 'referral.view')->orderByDesc('seq')->first();
    expect($e->actor_id)->toBe($c->id)->and($e->actor_role)->toBe('clinician')->and($e->facility_id)->toBe($this->A->id)->and($e->subject_id)->toBe($id);
    foreach (['referral.create', 'consent.record', 'referral.status'] as $a) {
        expect(actions())->toContain($a);
    }
});

it('keeps an intact hash chain with contiguous sequence numbers', function () {
    acceptedReferral($this->clin, $this->coord);
    signedIn($this, $this->clin)->get('/referrals');
    $v = AuditLog::verify();
    expect($v['ok'])->toBeTrue()->and($v['checked'])->toBeGreaterThan(5);
    $rows = AuditEntry::query()->orderBy('seq')->get();
    expect($rows->pluck('seq')->all())->toBe(range(1, $rows->count()));
    expect($rows[0]->prev_hash)->toBe(AuditLog::GENESIS);
    for ($i = 1; $i < $rows->count(); $i++) {
        expect($rows[$i]->prev_hash)->toBe($rows[$i - 1]->hash);
    }
    $this->artisan('audit:verify')->assertSuccessful();
    signedIn($this, $this->admin)->get('/admin/audit?verify=1')->assertOk()->assertSee('Chain intact');
});

it('refuses to update or delete audit rows (model guard and database triggers)', function () {
    acceptedReferral($this->clin, $this->coord);
    $e = AuditEntry::query()->first();
    expect(fn () => $e->update(['action' => 'x']))->toThrow(LogicException::class);
    expect(fn () => $e->delete())->toThrow(LogicException::class);
    expect(fn () => DB::table('audit_entries')->where('id', $e->id)->update(['action' => 'tampered']))->toThrow(Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('audit_entries')->where('id', $e->id)->delete())->toThrow(Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('audit_entries')->delete())->toThrow(Illuminate\Database\QueryException::class);
    expect(AuditLog::verify()['ok'])->toBeTrue();
});

it('detects tampering, deletion and forged hashes if the database protections are bypassed', function () {
    acceptedReferral($this->clin, $this->coord);
    signedIn($this, $this->clin)->get('/referrals');
    signedIn($this, $this->clin)->get('/referrals/new');
    $n = AuditEntry::query()->count();
    expect($n)->toBeGreaterThan(6);
    DB::unprepared('DROP TRIGGER audit_entries_no_update');
    DB::unprepared('DROP TRIGGER audit_entries_no_delete');

    // 1. altered content
    $row = AuditEntry::query()->where('seq', 3)->first();
    DB::table('audit_entries')->where('id', $row->id)->update(['action' => 'something.else']);
    $v = AuditLog::verify();
    expect($v['ok'])->toBeFalse()->and($v['broken_at'])->toBe(3);
    DB::table('audit_entries')->where('id', $row->id)->update(['action' => $row->action]);
    expect(AuditLog::verify()['ok'])->toBeTrue();

    // 2. deleted row -> gap
    $gone = AuditEntry::query()->where('seq', 4)->first()->getAttributes();
    DB::table('audit_entries')->where('seq', 4)->delete();
    $v = AuditLog::verify();
    expect($v['ok'])->toBeFalse()->and($v['broken_at'])->toBe(4);
    DB::table('audit_entries')->insert($gone);
    expect(AuditLog::verify()['ok'])->toBeTrue();

    // 3. re-written hash that still lies about content
    $row5 = AuditEntry::query()->where('seq', 5)->first();
    DB::table('audit_entries')->where('id', $row5->id)->update(['reason' => 'forged', 'hash' => str_repeat('a', 64)]);
    $v = AuditLog::verify();
    expect($v['ok'])->toBeFalse()->and($v['broken_at'])->toBe(5);
    $this->artisan('audit:verify')->assertFailed();
});

it('is written inside the same transaction as the change it records', function () {
    $r = sentReferral($this->clin, $this->B);
    $count = AuditEntry::query()->count();
    try {
        DB::transaction(function () use ($r) {
            AuditLog::record('test.rolled_back');
            throw new RuntimeException('abort');
        });
    } catch (RuntimeException) {
    }
    expect(AuditEntry::query()->count())->toBe($count);
});
