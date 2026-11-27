<?php

use App\Domain\AttachmentService;
use App\Domain\Notifier;
use App\Domain\ReferralService;
use App\Enums\Role;
use App\Mail\NeutralNoticeMail;
use App\Models\AuditEntry;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\UserNotice;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

const NAME = 'Zebedee Quillfeather';
const IDV = 'SYNID-777123';
const REASON = 'Quokka reason text';
const NOTE = 'Quokka clinical note';
const MSG = 'Quokka message body';

beforeEach(function () {
    Storage::fake('local');
    $this->from = facility(['name' => 'Origin HC']);
    $this->to = facility(['name' => 'Destination Hospital']);
    $this->clin = user(Role::Clinician, $this->from);
    $this->coord = user(Role::Coordinator, $this->to);
    $this->patient = patientFor($this->clin, ['full_name' => NAME, 'identifier' => IDV]);
    Mail::fake();
    $this->ref = acceptedReferral($this->clin, $this->coord, ['patient' => $this->patient, 'reason_text' => REASON, 'clinical_note' => NOTE]);
    ReferralService::postMessage($this->coord, $this->ref, MSG);
    ReferralService::schedule($this->coord, $this->ref, now()->addDay());
    ReferralService::markAttended($this->coord, $this->ref);
    ReferralService::complete($this->coord, $this->ref, 'treated_discharged', 'Quokka summary text');
    test()->be($this->clin);
    $png = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($png);
    $this->png = ob_get_clean();
    $f = UploadedFile::fake()->createWithContent('Zebedee-Quillfeather-scan.png', $this->png);
    // upload while still open: use a second open referral
    $this->open = sentReferral($this->clin, $this->to, ['patient' => $this->patient, 'reason_text' => REASON]);
    $this->doc = AttachmentService::store($this->clin, $this->open, $f);
});

function rawColumns(): array
{
    return [
        'patients' => ['full_name', 'date_of_birth', 'identifier'],
        'referrals' => ['reason_text', 'clinical_note', 'form_data', 'terminal_note'],
        'messages' => ['body'],
        'counter_referrals' => ['summary', 'follow_up'],
        'attachments' => ['original_name'],
        'users' => ['mfa_secret'],
    ];
}

it('stores clinical fields and identifiers as AES-256-GCM ciphertext in the raw database columns', function () {
    expect(strtoupper(config('app.cipher')))->toBe('AES-256-GCM');
    $plain = [NAME, IDV, REASON, NOTE, MSG, 'Quokka summary text', 'Zebedee-Quillfeather-scan', '1990-05-05', 'Synthetic treatment'];
    $checked = 0;
    foreach (rawColumns() as $table => $cols) {
        foreach (DB::table($table)->get() as $row) {
            foreach ($cols as $c) {
                $v = $row->$c;
                if ($v === null) {
                    continue;
                }
                foreach ($plain as $p) {
                    expect($v)->not->toContain($p, "$table.$c");
                }
                $payload = json_decode(base64_decode($v), true);
                expect($payload)->toBeArray()->toHaveKeys(['iv', 'value', 'tag'], "$table.$c is an authenticated-encryption envelope");
                $checked++;
            }
        }
    }
    expect($checked)->toBeGreaterThan(10);
    // blind indexes are keyed hashes, not the identifier
    expect(DB::table('patients')->value('identifier_hash'))->toMatch('/^[0-9a-f]{64}$/')->not->toBe(hash('sha256', IDV));
    // and it round-trips through the model
    expect(Patient::query()->withoutGlobalScopes()->find($this->patient->id)->full_name)->toBe(NAME);
});

it('stores uploaded documents encrypted on the private disk', function () {
    $stored = Storage::disk('local')->get($this->doc->path);
    expect($stored)->not->toContain("\x89PNG")->not->toContain('Zebedee')->and(AttachmentService::read($this->doc))->toContain("\x89PNG");
    expect($this->doc->path)->toStartWith('attachments/')->and(file_exists(public_path($this->doc->path)))->toBeFalse();
});

it('detects tampering with a ciphertext column (authenticated encryption)', function () {
    $raw = DB::table('patients')->where('id', $this->patient->id)->value('full_name');
    $p = json_decode(base64_decode($raw), true);
    $p['value'] = base64_encode(str_repeat('A', 12));
    DB::table('patients')->where('id', $this->patient->id)->update(['full_name' => base64_encode(json_encode($p))]);
    expect(fn () => Patient::query()->withoutGlobalScopes()->find($this->patient->id)->full_name)->toThrow(DecryptException::class);
});

it('masks identity by default everywhere and reveals only with a reason, logged', function () {
    $this->be($this->clin);
    $page = signedIn($this, $this->clin)->get("/referrals/{$this->ref->id}")->assertOk()->assertSee('Z. Q.')->assertDontSee('Zebedee')->assertDontSee(IDV);
    signedIn($this, $this->clin)->post("/referrals/{$this->ref->id}/reveal", [])->assertSessionHasErrors('reason');
    expect(AuditEntry::query()->where('action', 'patient.reveal')->count())->toBe(0);
    signedIn($this, $this->clin)->post("/referrals/{$this->ref->id}/reveal", ['reason' => 'verify_identity'])->assertRedirect();
    $entry = AuditEntry::query()->where('action', 'patient.reveal')->first();
    expect($entry->reason)->toBe('verify_identity')->and($entry->actor_id)->toBe($this->clin->id)->and($entry->subject_id)->toBe($this->ref->id);
    // the reveal is held in the session; a follow-up request shows the name, then it expires
    $s = ['reveal.'.$this->ref->id => time() + 300];
    signedIn($this, $this->clin)->withSession($s)->get("/referrals/{$this->ref->id}")->assertSee('Zebedee Quillfeather')->assertSee(IDV);
    signedIn($this, $this->clin)->withSession(['reveal.'.$this->ref->id => time() - 1])->get("/referrals/{$this->ref->id}")->assertDontSee('Zebedee');
    signedIn($this, $this->clin)->post("/referrals/{$this->ref->id}/hide");
    // the receiving facility can reveal only after acceptance, and also with a logged reason
    signedIn($this, $this->coord)->post("/referrals/{$this->ref->id}/reveal", ['reason' => 'prepare_visit'])->assertRedirect();
    expect(AuditEntry::query()->where('action', 'patient.reveal')->count())->toBe(2);
});

it('refuses identity reveal before acceptance and for outsiders', function () {
    $sent = sentReferral($this->clin, $this->to, ['patient' => patientFor($this->clin)]);
    signedIn($this, $this->coord)->post("/referrals/{$sent->id}/reveal", ['reason' => 'prepare_visit'])->assertForbidden();
    $out = user(Role::Clinician);
    signedIn($this, $out)->post("/referrals/{$this->ref->id}/reveal", ['reason' => 'prepare_visit'])->assertNotFound();
});

it('puts no patient data in any URL, page title, link target or form action', function () {
    $pages = ['/', '/referrals', '/referrals/new', '/inbox', '/directory', '/access', '/notifications', '/settings', '/data-requests',
        "/referrals/{$this->ref->id}", "/referrals/{$this->ref->id}?tab=messages", "/referrals/{$this->ref->id}?tab=history", "/directory/{$this->to->id}"];
    $secrets = [NAME, 'Zebedee', 'Quillfeather', IDV, REASON, NOTE, MSG, '1990-05-05', 'Quokka'];
    foreach ([$this->clin, $this->coord] as $u) {
        foreach ($pages as $path) {
            $res = signedIn($this, $u)->get($path);
            if ($res->status() !== 200) {
                continue;
            }
            $html = $res->getContent();
            preg_match('#<title>(.*?)</title>#s', $html, $t);
            expect($t[1] ?? '')->toMatch('/^[A-Za-zÀ-ž ,\']+ · ReferralFlow$/u', "title of $path");
            preg_match_all('#(?:href|action|src)="([^"]*)"#', $html, $urls);
            foreach ($urls[1] as $url) {
                foreach ($secrets as $s) {
                    expect(urldecode(html_entity_decode($url)))->not->toContain($s, "URL $url on $path");
                }
            }
            if ($path !== "/referrals/{$this->ref->id}" && $path !== "/referrals/{$this->ref->id}?tab=messages" && $path !== "/referrals/{$this->ref->id}?tab=history") {
                foreach ([NAME, IDV, '1990-05-05'] as $s) {
                    expect($html)->not->toContain($s, "$path leaks identity");
                }
            }
            expect(urldecode($path))->not->toContain('Zebedee');
        }
    }
});

it('sends notifications and emails with no patient or clinical content', function () {
    $emails = Mail::sent(NeutralNoticeMail::class);
    expect($emails->count())->toBeGreaterThan(3);
    foreach ($emails as $m) {
        $body = $m->render();
        foreach ([NAME, 'Zebedee', IDV, REASON, NOTE, MSG, 'Quokka', 'Origin HC', 'Destination Hospital', 'Synthetic reason'] as $s) {
            expect($body)->not->toContain($s);
        }
        expect($m->envelope()->subject)->toBe('ReferralFlow: an update is waiting');
    }
    foreach (UserNotice::query()->get() as $n) {
        foreach ([NAME, IDV, REASON, NOTE, MSG, 'Quokka', 'Origin HC', 'Destination Hospital'] as $s) {
            expect($n->text)->not->toContain($s);
        }
        expect($n->text)->toBe(Notifier::TEXT[$n->kind]);
    }
    signedIn($this, $this->clin)->get('/notifications')->assertOk()->assertDontSee('Zebedee')->assertDontSee('Quokka');
});

it('keeps patient data out of the audit log', function () {
    $dump = AuditEntry::query()->get()->map(fn ($e) => json_encode($e->getAttributes()))->join("\n");
    foreach ([NAME, 'Zebedee', 'Quillfeather', IDV, REASON, NOTE, MSG, 'Quokka'] as $s) {
        expect($dump)->not->toContain($s);
    }
    $csv = signedIn($this, user(Role::PlatformAdmin))->post('/admin/audit/export')->assertOk()->getContent();
    expect($csv)->not->toContain('Zebedee')->not->toContain('Quokka');
});

it('writes no patient data to the application log, even when requests fail', function () {
    $log = storage_path('logs/privacy-test-'.uniqid().'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $log, 'app.debug' => false]);
    app('log')->forgetChannel('single');
    // a spread of failing and succeeding requests
    signedIn($this, $this->clin)->get('/referrals/'.str_repeat('1', 26)); // locked
    signedIn($this, $this->clin)->get('/no-such-page');                  // 404
    signedIn($this, $this->clin)->post('/referrals', ['patient_mode' => 'new', 'full_name' => NAME, 'identifier' => IDV, 'reason_text' => REASON]); // validation errors
    signedIn($this, $this->coord)->post("/referrals/{$this->ref->id}/decline", ['reason' => 'no_capacity']);     // illegal move
    Route::get('/_boom', fn () => throw new RuntimeException('boom '.NAME.' '.IDV))->middleware('web');
    $res = signedIn($this, $this->clin)->get('/_boom');
    $res->assertStatus(500);
    expect($res->getContent())->not->toContain('Zebedee')->not->toContain('boom')->not->toContain('RuntimeException');
    $contents = file_exists($log) ? file_get_contents($log) : '';
    // framework exception reports include the exception MESSAGE: only our deliberate probe may appear, never row data
    $contents = str_replace('boom '.NAME.' '.IDV, '[probe]', $contents);
    foreach ([REASON, NOTE, MSG, 'Quokka'] as $s) {
        expect($contents)->not->toContain($s);
    }
    @unlink($log);
});

it('renders error pages without patient data or technical detail', function () {
    config(['app.debug' => false]);
    foreach (['/no-such-page', '/referrals/'.str_repeat('0', 26)] as $p) {
        $c = signedIn($this, $this->clin)->get($p)->getContent();
        expect($c)->not->toContain('Zebedee')->not->toContain('Quokka')->not->toContain('Stack trace')->not->toContain('vendor/');
    }
    foreach ([403, 404, 419, 429, 500, 503] as $code) {
        $html = view("errors.$code")->render();
        expect($html)->toContain('ReferralFlow')->not->toContain('Exception')->not->toContain(NAME);
    }
});

it('ships with debug off in the example environment', function () {
    expect(file_get_contents(base_path('.env.example')))->toContain('APP_DEBUG=false')->toContain('SESSION_ENCRYPT=true')->toContain('APP_CIPHER=AES-256-GCM');
});

it('gives every attachment download an opaque name and audits the open', function () {
    $acc = ReferralService::class;
    $res = signedIn($this, $this->coord)->get("/referrals/{$this->open->id}/documents/{$this->doc->id}");
    $res->assertForbidden(); // receiver has only the minimum until acceptance
    $res = signedIn($this, $this->clin)->get("/referrals/{$this->open->id}/documents/{$this->doc->id}");
    $res->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($res->headers->get('Content-Disposition'))->not->toContain('Zebedee')->toContain('document-');
    expect(AuditEntry::query()->where('action', 'attachment.open')->count())->toBe(1);
});
