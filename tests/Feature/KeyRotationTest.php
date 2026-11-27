<?php

use App\Enums\Role;
use App\Models\Patient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;

it('re-encrypts data under a new key after rotation, using the previous key to read old data', function () {
    $u = user(Role::Clinician);
    $p = patientFor($u, ['full_name' => 'Rotation Subject', 'identifier' => 'SYN-ROT-1']);
    $oldKey = base64_decode(substr(config('app.key'), 7));
    $newKeyB64 = 'base64:'.base64_encode(random_bytes(32));

    // 1. rotate: new APP_KEY, old key moved to previous keys
    config(['app.key' => $newKeyB64, 'app.previous_keys' => [config('app.key')]]);
    $prev = 'base64:'.base64_encode($oldKey);
    config(['app.previous_keys' => [$prev]]);
    app()->forgetInstance('encrypter');
    Facade::clearResolvedInstance('encrypter');
    app()->singleton('encrypter', fn ($app) => (new Encrypter(base64_decode(substr($newKeyB64, 7)), 'AES-256-GCM'))->previousKeys([$oldKey]));

    // old ciphertext is still readable (via the previous key) before re-encryption
    $before = DB::table('patients')->where('id', $p->id)->value('full_name');
    expect(Patient::query()->withoutGlobalScopes()->find($p->id)->full_name)->toBe('Rotation Subject');

    $this->artisan('encryption:rotate --dry-run')->assertSuccessful();
    expect(DB::table('patients')->where('id', $p->id)->value('full_name'))->toBe($before);
    $this->artisan('encryption:rotate')->assertSuccessful();
    $after = DB::table('patients')->where('id', $p->id)->value('full_name');
    expect($after)->not->toBe($before);

    // the new ciphertext decrypts with the new key alone; the old key alone no longer can
    $newOnly = new Encrypter(base64_decode(substr($newKeyB64, 7)), 'AES-256-GCM');
    expect($newOnly->decryptString($after))->toBe('Rotation Subject');
    $oldOnly = new Encrypter($oldKey, 'AES-256-GCM');
    expect(fn () => $oldOnly->decryptString($after))->toThrow(DecryptException::class);
    expect(DB::table('users')->where('id', $u->id)->value('mfa_secret'))->not->toBeNull();
});
