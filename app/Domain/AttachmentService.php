<?php

namespace App\Domain;

use App\Models\Attachment;
use App\Models\Referral;
use App\Models\User;
use App\Security\AuditLog;
use App\Security\ReferralAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Validated, scanned, encrypted-at-rest private documents. Never publicly addressable. */
final class AttachmentService
{
    private const MAGIC = [
        'application/pdf' => "%PDF-",
        'image/jpeg' => "\xFF\xD8\xFF",
        'image/png' => "\x89PNG\r\n\x1a\n",
    ];

    public static function sniff(string $bytes): ?string
    {
        foreach (self::MAGIC as $mime => $sig) {
            if (str_starts_with($bytes, $sig)) {
                return $mime;
            }
        }

        return null;
    }

    public static function store(User $u, Referral $r, UploadedFile $file): Attachment
    {
        if (ReferralAccess::side($u, $r) !== 'referrer' || ReferralAccess::level($u, $r) !== ReferralAccess::FULL || ! $u->isClinical() || $r->isTerminal()) {
            throw new AuthorizationException('Documents can be added by the referring facility while the referral is open.');
        }
        $cfg = config('referralflow.uploads');
        if ($r->attachments()->count() >= $cfg['max_per_referral']) {
            throw ValidationException::withMessages(['document' => 'A referral can have up to '.$cfg['max_per_referral'].' documents.']);
        }
        if (! $file->isValid() || $file->getSize() > $cfg['max_bytes'] || $file->getSize() === 0) {
            throw ValidationException::withMessages(['document' => 'File is empty or larger than '.($cfg['max_bytes'] / 1048576).' MB.']);
        }
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = self::sniff($bytes);
        if (! $mime || ! isset($cfg['types'][$mime])) {
            throw ValidationException::withMessages(['document' => 'This file type is not accepted. Use PDF, JPEG or PNG.']);
        }
        $declared = (string) $file->getClientMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        if (($declared !== '' && $declared !== 'application/octet-stream' && $declared !== $mime) || ($ext !== '' && ! self::extMatches($ext, $mime))) {
            throw ValidationException::withMessages(['document' => 'File content does not match its type.']);
        }
        $bytes = match ($mime) {
            'application/pdf' => self::checkPdf($bytes),
            default => self::reencode($bytes, $mime),
        };

        $scanner = app(VirusScanner::class);
        if ($cfg['av_mode'] === 'required' && $scanner instanceof NullScanner) {
            throw ValidationException::withMessages(['document' => 'Uploads are disabled: no antivirus scanner is configured.']);
        }
        $scan = $scanner->scan($bytes);
        if ($scan === 'infected') {
            AuditLog::record('attachment.blocked', 'referral', $r->id, ['reason' => 'infected']);
            throw ValidationException::withMessages(['document' => 'This file was blocked by the virus scan.']);
        }

        $a = new Attachment([
            'referral_id' => $r->id,
            'uploaded_by' => $u->id,
            'facility_id' => $u->facility_id,
            'original_name' => $file->getClientOriginalName() ?: 'document',
            'mime' => $mime,
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'sha1' => sha1($bytes),
            'scan_status' => $scan,
            'path' => '',
        ]);
        $a->id = strtolower((string) \Illuminate\Support\Str::ulid());
        $a->path = 'attachments/'.$a->id.'.enc';
        Storage::disk('local')->put($a->path, Crypt::encryptString(base64_encode($bytes)));
        $a->save();
        AuditLog::record('attachment.add', 'attachment', $a->id, ['referral' => $r->id, 'mime' => $mime, 'size' => $a->size]);

        return $a;
    }

    /** Decrypted bytes. Caller must have established access and audited. */
    public static function read(Attachment $a): string
    {
        return (string) base64_decode(Crypt::decryptString(Storage::disk('local')->get($a->path)));
    }

    private static function extMatches(string $ext, string $mime): bool
    {
        return match ($mime) {
            'application/pdf' => $ext === 'pdf',
            'image/jpeg' => in_array($ext, ['jpg', 'jpeg'], true),
            'image/png' => $ext === 'png',
            default => false,
        };
    }

    private static function checkPdf(string $b): string
    {
        if (! str_contains(substr($b, -1024), '%%EOF')) {
            throw ValidationException::withMessages(['document' => 'File content does not match its type.']);
        }
        if (preg_match('#/(JavaScript|JS|Launch|EmbeddedFile|OpenAction)\b#', $b)) {
            throw ValidationException::withMessages(['document' => 'PDFs with scripts or embedded files are not accepted.']);
        }

        return $b;
    }

    /** Re-encoding drops EXIF and any trailing payload. */
    private static function reencode(string $b, string $mime): string
    {
        $img = @imagecreatefromstring($b);
        if (! $img) {
            throw ValidationException::withMessages(['document' => 'File content does not match its type.']);
        }
        ob_start();
        $mime === 'image/png' ? imagepng($img) : imagejpeg($img, null, 88);
        $out = (string) ob_get_clean();
        imagedestroy($img);

        return $out;
    }
}
