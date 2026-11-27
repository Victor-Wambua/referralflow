<?php

use App\Domain\AttachmentService;
use App\Domain\NullScanner;
use App\Domain\ReferralService;
use App\Domain\VirusScanner;
use App\Enums\Role;
use App\Models\Attachment;
use App\Models\AuditEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->A = facility();
    $this->B = facility();
    $this->clin = user(Role::Clinician, $this->A);
    $this->coord = user(Role::Coordinator, $this->B);
    $this->ref = sentReferral($this->clin, $this->B);
    $this->be($this->clin);
});

function upload(string $name, string $bytes, ?string $mime = null): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $bytes);
}

function png(int $w = 4): string
{
    $i = imagecreatetruecolor($w, $w);
    ob_start();
    imagepng($i);

    return ob_get_clean();
}

function jpeg(): string
{
    $i = imagecreatetruecolor(8, 8);
    ob_start();
    imagejpeg($i);

    return ob_get_clean();
}

function pdf(string $extra = ''): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n$extra\ntrailer<<>>\n%%EOF\n";
}

it('accepts PDF, JPEG and PNG and stores them encrypted with a hash', function () {
    foreach ([['a.pdf', pdf()], ['b.jpg', jpeg()], ['c.png', png()]] as [$n, $b]) {
        $a = AttachmentService::store($this->clin, $this->ref, upload($n, $b));
        expect($a->sha256)->toMatch('/^[0-9a-f]{64}$/')->and(Storage::disk('local')->exists($a->path))->toBeTrue()->and($a->scan_status)->toBe('not_scanned');
        expect(AttachmentService::read($a))->not->toBe('');
    }
    expect($this->ref->attachments()->count())->toBe(3);
});

it('rejects content that does not match an allowed type, whatever the file name says', function () {
    $bad = [
        'text renamed to pdf' => ['notes.pdf', "just some text\n"],
        'php renamed to png' => ['shell.png', '<?php system($_GET["c"]); ?>'],
        'exe disguised' => ['setup.pdf', "MZ\x90\x00\x03\x00\x00\x00 fake exe"],
        'html as jpg' => ['page.jpg', '<html><script>alert(1)</script></html>'],
        'gif not allowed' => ['a.gif', "GIF89a\x01\x00\x01\x00"],
        'truncated pdf' => ['t.pdf', "%PDF-1.4\nno end marker"],
        'png with jpg extension' => ['x.jpg', png()],
        'pdf with png extension' => ['x.png', pdf()],
        'empty' => ['e.pdf', ''],
        'svg' => ['i.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'],
    ];
    foreach ($bad as $label => [$name, $bytes]) {
        expect(fn () => AttachmentService::store($this->clin, $this->ref, upload($name, $bytes)))->toThrow(ValidationException::class);
    }
    expect(Attachment::query()->withoutGlobalScopes()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('attachments'))->toBe([]);
});

it('rejects PDFs with active content and oversize files', function () {
    foreach (['/JavaScript (app.alert(1))', '/OpenAction 1 0 R', '/Launch /Win', '/EmbeddedFile'] as $x) {
        expect(fn () => AttachmentService::store($this->clin, $this->ref, upload('a.pdf', pdf($x))))->toThrow(ValidationException::class);
    }
    $big = pdf(str_repeat('A', config('referralflow.uploads.max_bytes') + 10));
    expect(fn () => AttachmentService::store($this->clin, $this->ref, upload('big.pdf', $big)))->toThrow(ValidationException::class);
});

it('allows at most five documents per referral', function () {
    for ($i = 0; $i < 5; $i++) {
        AttachmentService::store($this->clin, $this->ref, upload("d$i.png", png($i + 2)));
    }
    expect(fn () => AttachmentService::store($this->clin, $this->ref, upload('six.png', png(9))))->toThrow(ValidationException::class);
});

it('re-encodes images so metadata and appended payloads are dropped', function () {
    $marker = 'TRAILING_SECRET_PAYLOAD_123';
    $a = AttachmentService::store($this->clin, $this->ref, upload('p.png', png().$marker));
    expect(AttachmentService::read($a))->not->toContain($marker)->and(AttachmentService::sniff(AttachmentService::read($a)))->toBe('image/png');
    $jpg = jpeg();
    $withExif = substr($jpg, 0, 2)."\xFF\xE1\x00\x16Exif\x00\x00GPS_COORDS_HERE!!".substr($jpg, 2);
    $b = AttachmentService::store($this->clin, $this->ref, upload('p.jpg', $withExif));
    expect(AttachmentService::read($b))->not->toContain('GPS_COORDS_HERE');
});

it('calls the antivirus hook and blocks infected files; required mode refuses without a real scanner', function () {
    app()->bind(VirusScanner::class, fn () => new class implements VirusScanner
    {
        public function isOperational(): bool
        {
            return true;
        }

        public function scan(string $contents): string
        {
            return str_contains($contents, 'EICAR') ? 'infected' : 'clean';
        }
    });
    expect(AttachmentService::store($this->clin, $this->ref, upload('ok.pdf', pdf()))->scan_status)->toBe('clean');
    expect(fn () => AttachmentService::store($this->clin, $this->ref, upload('bad.pdf', pdf('EICAR'))))->toThrow(ValidationException::class, 'This file was blocked by the virus scan.');
    expect(AuditEntry::query()->where('action', 'attachment.blocked')->count())->toBe(1);
    app()->bind(VirusScanner::class, NullScanner::class);
    config(['referralflow.uploads.av_mode' => 'required']);
    expect(fn () => AttachmentService::store($this->clin, $this->ref, upload('ok2.pdf', pdf('x'))))->toThrow(ValidationException::class, 'no antivirus scanner');
});

it('lets the referring facility upload while open, and nobody else', function () {
    expect(fn () => AttachmentService::store($this->coord, $this->ref, upload('a.png', png())))->toThrow(AuthorizationException::class);
    expect(fn () => AttachmentService::store(user(Role::Clinician), $this->ref, upload('a.png', png())))->toThrow(AuthorizationException::class);
    ReferralService::cancel($this->clin, $this->ref, 'no_longer_needed');
    expect(fn () => AttachmentService::store($this->clin, $this->ref->fresh(), upload('a.png', png())))->toThrow(AuthorizationException::class);
});

it('serves documents only to authorised, audited requests: not before acceptance, not to outsiders, never by name', function () {
    $a = AttachmentService::store($this->clin, $this->ref, upload('Patient-Name-Scan.png', png()));
    $url = "/referrals/{$this->ref->id}/documents/{$a->id}";
    signedIn($this, $this->clin)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    signedIn($this, $this->coord)->get($url)->assertForbidden();           // minimum-necessary until accepted
    signedIn($this, user(Role::Clinician))->get($url)->assertNotFound();
    signedIn($this, user(Role::NetworkAnalyst))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertRedirect('/login');
    $this->be($this->coord);
    ReferralService::accept($this->coord, $this->ref->fresh());
    $res = signedIn($this, $this->coord)->get($url)->assertOk();
    expect($res->headers->get('Content-Disposition'))->not->toContain('Patient-Name')->and($res->headers->get('Content-Security-Policy'))->toContain('sandbox');
    expect(AuditEntry::query()->where('action', 'attachment.open')->count())->toBe(2);
    // no direct public path, no traversal
    signedIn($this, $this->coord)->get("/referrals/{$this->ref->id}/documents/..%2F..%2F.env")->assertNotFound();
    signedIn($this, $this->coord)->get("/referrals/{$this->ref->id}/documents/".str_repeat('0', 26))->assertNotFound();
    expect(file_exists(public_path('storage')))->toBeFalse();
});

it('uploads through the UI with clear errors and rejects it for the receiving side', function () {
    signedIn($this, $this->clin)->post("/referrals/{$this->ref->id}/documents", ['document' => upload('x.pdf', 'not really a pdf')])->assertSessionHasErrors('document');
    signedIn($this, $this->clin)->post("/referrals/{$this->ref->id}/documents", ['document' => upload('x.pdf', pdf())])->assertSessionHasNoErrors();
    signedIn($this, $this->coord)->post("/referrals/{$this->ref->id}/documents", ['document' => upload('y.pdf', pdf())])->assertForbidden();
    signedIn($this, $this->clin)->get("/referrals/{$this->ref->id}")->assertSee('Document 1')->assertSee('Not scanned')->assertDontSee('x.pdf');
});
