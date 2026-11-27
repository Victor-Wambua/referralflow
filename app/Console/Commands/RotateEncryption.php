<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Security\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Re-encrypts every encrypted column and stored document under the CURRENT application key.
 * Procedure (docs/SECURITY.md): generate a new APP_KEY, put the old one in APP_PREVIOUS_KEYS, deploy,
 * run this command, verify, then remove the old key from APP_PREVIOUS_KEYS.
 * Blind-index (HMAC) values are NOT touched here: changing BLIND_INDEX_KEY needs a separate rebuild.
 */
class RotateEncryption extends Command
{
    protected $signature = 'encryption:rotate {--dry-run}';

    protected $description = 'Re-encrypt all encrypted columns and documents with the current APP_KEY';

    private const COLUMNS = [
        'patients' => ['full_name', 'date_of_birth', 'identifier'],
        'referrals' => ['reason_text', 'clinical_note', 'form_data', 'terminal_note'],
        'messages' => ['body'],
        'counter_referrals' => ['summary', 'follow_up'],
        'consents' => ['witness'],
        'referral_events' => ['note'],
        'attachments' => ['original_name'],
        'users' => ['mfa_secret', 'mfa_recovery_codes'],
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = 0;
        $values = 0;
        foreach (self::COLUMNS as $table => $cols) {
            $pk = 'id';
            DB::table($table)->orderBy($pk)->each(function ($row) use ($table, $cols, $pk, $dry, &$rows, &$values) {
                $update = [];
                foreach ($cols as $c) {
                    if ($row->$c === null) {
                        continue;
                    }
                    $plain = Crypt::decryptString($row->$c); // falls back to APP_PREVIOUS_KEYS
                    $update[$c] = Crypt::encryptString($plain);
                    $values++;
                }
                if ($update && ! $dry) {
                    DB::table($table)->where($pk, $row->$pk)->update($update);
                }
                $rows++;
            });
        }
        $files = 0;
        Attachment::query()->withoutGlobalScopes()->whereNull('purged_at')->each(function (Attachment $a) use ($dry, &$files) {
            $plain = Crypt::decryptString(Storage::disk('local')->get($a->path));
            if (! $dry) {
                Storage::disk('local')->put($a->path, Crypt::encryptString($plain));
            }
            $files++;
        });
        if (! $dry) {
            AuditLog::record('encryption.rotate', 'system', null, ['values' => $values, 'files' => $files]);
        }
        $this->line(($dry ? 'Dry run: ' : '')."$values values in $rows rows and $files documents re-encrypted under the current key.");

        return self::SUCCESS;
    }
}
