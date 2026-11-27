<?php

use App\Domain\AttachmentService;
use App\Domain\DataRequestService;
use App\Domain\MetricsService;
use App\Domain\ReferralService;
use App\Domain\RetentionService;
use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\DataRequest;
use App\Models\LegalHold;
use App\Models\Message;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\Setting;
use App\Security\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->A = facility();
    $this->B = facility();
    $this->clin = user(Role::Clinician, $this->A);
    $this->coord = user(Role::Coordinator, $this->B);
    $this->adminA = user(Role::FacilityAdmin, $this->A);
    Setting::put('retention.closed_referral_days', 30);
    Setting::put('retention.message_days', 30);
    Setting::put('retention.attachment_days', 30);
    Setting::put('retention.draft_days', 10);
});

/** A completed referral whose completion happened $daysAgo days ago. */
function closedReferral(int $daysAgo, array $patientOver = [])
{
    $t = test();
    $p = patientFor($t->clin, $patientOver);
    $img = imagecreatetruecolor(3, 3);
    $r = sentReferral($t->clin, $t->B, ['patient' => $p, 'reason_text' => 'Quokka reason']);
    $t->be($t->clin);
    AttachmentService::store($t->clin, $r, UploadedFile::fake()->createWithContent('a.png', (function () use ($img) {
        ob_start();
        imagepng($img);

        return ob_get_clean();
    })()));
    $t->be($t->coord);
    ReferralService::accept($t->coord, $r);
    ReferralService::postMessage($t->coord, $r, 'Quokka message');
    ReferralService::schedule($t->coord, $r, now()->addDay());
    ReferralService::markAttended($t->coord, $r);
    ReferralService::complete($t->coord, $r, 'treated_discharged', 'Quokka summary');
    $when = now()->subDays($daysAgo);
    DB::table('referrals')->where('id', $r->id)->update(['completed_at' => $when]);

    return $r->fresh();
}

it('purges clinical content from old closed referrals but keeps the non-identifying skeleton', function () {
    $old = closedReferral(45);
    $young = closedReferral(5);
    $docPath = $old->attachments()->first()->path;
    expect(Storage::disk('local')->exists($docPath))->toBeTrue();

    $res = RetentionService::purge();
    expect($res['referrals'])->toBe(1)->and($res['attachments'])->toBe(1)->and($res['messages'])->toBe(1);

    $old->refresh();
    expect($old->purged_at)->not->toBeNull()->and($old->reason_text)->toBeNull()->and($old->clinical_note)->toBeNull()->and($old->form_data)->toBeNull()
        ->and($old->patient_id)->toBeNull()->and($old->patient_sex)->toBeNull();
    // skeleton stays for aggregates
    expect($old->status)->toBe('completed')->and($old->priority)->toBe('urgent')->and($old->reason_code)->toBe('medical')->and($old->sent_at)->not->toBeNull();
    expect(Storage::disk('local')->exists($docPath))->toBeFalse();
    expect(TenantContext::asSystem(fn () => Message::query()->where('referral_id', $old->id)->count()))->toBe(0);
    expect(DB::table('counter_referrals')->where('referral_id', $old->id)->count())->toBe(0);
    // patient removed because nothing else refers to it
    expect(Patient::query()->withoutGlobalScopes()->count())->toBe(1);
    // the younger one is untouched
    $young->refresh();
    expect($young->purged_at)->toBeNull()->and($young->reason_text)->toBe('Quokka reason');
    expect(AuditEntry::query()->where('action', 'retention.purge_referral')->count())->toBe(1);
    // idempotent
    expect(RetentionService::purge()['referrals'])->toBe(0);
});

it('does not change aggregate metrics when content is purged', function () {
    $old = closedReferral(45);
    $from = now()->subDays(100);
    $to = now()->addDay();
    $before = MetricsService::compute($from, $to);
    RetentionService::purge();
    expect(MetricsService::compute($from, $to))->toEqual($before);
    // and the purged page shows the skeleton honestly
    signedIn($this, $this->clin)->get("/referrals/{$old->id}")->assertOk()->assertSee('Patient record removed under the retention policy')->assertDontSee('Quokka');
});

it('supports a dry run that changes nothing, and runs from the command', function () {
    $old = closedReferral(45);
    $this->artisan('retention:purge --dry-run')->assertSuccessful();
    expect($old->fresh()->purged_at)->toBeNull()->and($old->fresh()->reason_text)->toBe('Quokka reason');
    expect(RetentionService::purge(true)['referrals'])->toBe(1);
    $this->artisan('retention:purge')->assertSuccessful();
    expect($old->fresh()->purged_at)->not->toBeNull();
});

it('respects legal holds on a referral or a patient', function () {
    $held = closedReferral(45);
    $heldPatient = closedReferral(45);
    LegalHold::query()->create(['referral_id' => $held->id, 'reason' => 'Litigation', 'placed_by' => user(Role::PlatformAdmin)->id, 'placed_at' => now()]);
    DB::table('legal_holds')->insert(['patient_id' => TenantContext::asSystem(fn () => Referral::query()->find($heldPatient->id)->patient_id), 'reason' => 'Inquiry', 'placed_by' => 1, 'placed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $res = RetentionService::purge();
    expect($res['referrals'])->toBe(0)->and($res['held'])->toBe(2);
    expect($held->fresh()->purged_at)->toBeNull()->and($heldPatient->fresh()->purged_at)->toBeNull();
    // released holds no longer protect
    LegalHold::query()->update(['released_at' => now()]);
    expect(RetentionService::purge()['referrals'])->toBe(2);
});

it('removes stale unsent drafts but never touches sent or recent drafts, and never purges the audit log', function () {
    $old = draftReferral($this->clin, $this->B);
    DB::table('referrals')->where('id', $old->id)->update(['created_at' => now()->subDays(20)]);
    $recent = draftReferral($this->clin, $this->B);
    $sent = sentReferral($this->clin, $this->B);
    DB::table('referrals')->where('id', $sent->id)->update(['created_at' => now()->subDays(20)]);
    $audit = AuditEntry::query()->count();
    $res = RetentionService::purge();
    expect($res['drafts'])->toBe(1);
    TenantContext::asSystem(function () use ($old, $recent, $sent) {
        expect(Referral::query()->whereKey($old->id)->exists())->toBeFalse()->and(Referral::query()->whereKey($recent->id)->exists())->toBeTrue()->and(Referral::query()->whereKey($sent->id)->exists())->toBeTrue();
    });
    expect(AuditEntry::query()->count())->toBeGreaterThanOrEqual($audit);
    expect(config('referralflow.retention.audit_days'))->toBeNull();
});

it('lets the platform admin change validated retention settings, with re-authentication, and logs it', function () {
    $admin = user(Role::PlatformAdmin);
    $ok = ['closed_referral_days' => 400, 'message_days' => 200, 'attachment_days' => 300, 'draft_days' => 20, 'due_days' => 5, 'stale_days' => 20];
    signedIn($this, $admin)->post('/admin/retention', ['closed_referral_days' => 0] + $ok)->assertSessionHasErrors('closed_referral_days');
    signedIn($this, $admin)->post('/admin/retention', ['stale_days' => 3] + $ok)->assertSessionHasErrors('stale_days');
    signedIn($this, $admin)->post('/admin/retention', ['draft_days' => 'abc'] + $ok)->assertSessionHasErrors('draft_days');
    signedIn($this, $admin)->post('/admin/retention', $ok)->assertSessionHasNoErrors();
    expect(RetentionService::days('closed_referral_days'))->toBe(400)->and(App\Directory\Verification::staleDays())->toBe(20);
    expect(AuditEntry::query()->where('action', 'settings.update')->count())->toBe(1);
    signedIn($this, $admin)->get('/admin/retention')->assertOk()->assertSee('Confirm with counsel');
});

// ---- data export and deletion workflow ----

it('exports a patient’s data after the facility admin approves, once, and audits it', function () {
    $r = closedReferral(1, ['full_name' => 'Zebedee Export']);
    $patient = Patient::query()->withoutGlobalScopes()->first();
    $this->be($this->clin);
    $req = DataRequestService::open($this->clin, $patient, 'export');
    signedIn($this, $this->clin)->post("/data-requests/{$req->id}/approve")->assertForbidden(); // not an admin
    $res = signedIn($this, $this->adminA)->post("/data-requests/{$req->id}/approve")->assertOk();
    $json = $res->json();
    expect($json['patient']['name'])->toBe('Zebedee Export')->and($json['referrals'][0]['reason_text'])->toBe('Quokka reason')->and($json['notice'])->toContain('Synthetic');
    expect($req->fresh()->status)->toBe('completed');
    signedIn($this, $this->adminA)->post("/data-requests/{$req->id}/approve")->assertForbidden(); // cannot re-run
    expect(AuditEntry::query()->where('action', 'data_request.export_done')->where('flag', 'data_request')->count())->toBe(1);
    // another facility's admin cannot touch it
    signedIn($this, user(Role::FacilityAdmin, $this->B))->post("/data-requests/{$req->id}/approve")->assertNotFound();
});

it('deletes a patient’s data only when no hold or open referral blocks it', function () {
    $closed = closedReferral(1);
    $patient = Patient::query()->withoutGlobalScopes()->first();
    $open = sentReferral($this->clin, $this->B, ['patient' => $patient]);
    $this->be($this->clin);
    $req = DataRequestService::open($this->clin, $patient, 'deletion');
    [$r1] = DataRequestService::approve($this->adminA, $req);
    expect($r1->status)->toBe('blocked')->and($r1->decision_note)->toContain('Open referrals');
    expect(Patient::query()->withoutGlobalScopes()->count())->toBe(1);

    $this->be($this->clin);
    ReferralService::cancel($this->clin, $open, 'no_longer_needed');
    LegalHold::query()->create(['patient_id' => $patient->id, 'reason' => 'Court order', 'placed_by' => $this->adminA->id, 'placed_at' => now()]);
    $req2 = DataRequestService::open($this->clin, $patient, 'deletion');
    [$r2] = DataRequestService::approve($this->adminA, $req2);
    expect($r2->status)->toBe('blocked')->and($r2->decision_note)->toContain('legal hold');

    LegalHold::query()->update(['released_at' => now()]);
    $req3 = DataRequestService::open($this->clin, $patient, 'deletion');
    [$r3] = DataRequestService::approve($this->adminA, $req3, 'Approved by DPO');
    expect($r3->status)->toBe('completed');
    expect(Patient::query()->withoutGlobalScopes()->count())->toBe(0);
    TenantContext::asSystem(function () use ($closed, $open) {
        foreach ([$closed, $open] as $r) {
            $f = Referral::query()->find($r->id);
            expect($f->purged_at)->not->toBeNull()->and($f->reason_text)->toBeNull()->and($f->patient_id)->toBeNull();
        }
    });
    expect(AuditEntry::query()->where('action', 'data_request.delete_done')->count())->toBe(1);
});

it('lets only the right people open and reject requests', function () {
    $patient = patientFor($this->clin);
    $this->be($this->clin);
    expect(fn () => DataRequestService::open($this->coord, $patient, 'export'))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    $req = DataRequestService::open($this->clin, $patient, 'export');
    signedIn($this, $this->adminA)->post("/data-requests/{$req->id}/reject", [])->assertSessionHasErrors('note');
    signedIn($this, $this->adminA)->post("/data-requests/{$req->id}/reject", ['note' => 'Identity not verified'])->assertSessionHasNoErrors();
    expect($req->fresh()->status)->toBe('rejected');
    signedIn($this, $this->clin)->get('/data-requests')->assertOk()->assertSee('Identity not verified')->assertDontSee('Synthetic Person');
});
