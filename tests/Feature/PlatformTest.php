<?php

use App\Domain\Notifier;
use App\Enums\Role;
use App\Models\Patient;
use App\Models\Referral;
use App\Security\GoLiveGate;
use App\Security\ProductionGuard;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

it('refuses to start in production mode without the explicit acknowledgement, listing unmet prerequisites', function () {
    $run = fn (array $env) => Process::path(base_path())->env($env + ['APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'])->run('php artisan referralflow:ping 2>&1; php artisan list --raw 2>&1 | head -1');
    $res = Process::path(base_path())->env(['REFERRALFLOW_MODE' => 'production', 'REFERRALFLOW_PRODUCTION_ACK' => '', 'APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'APP_KEY' => config('app.key')])->run('php artisan golive:gate');
    expect($res->exitCode())->not->toBe(0);
    expect($res->output().$res->errorOutput())->toContain('refuses to start in production mode')->toContain('Legal review')->toContain('penetration test');
    $ok = Process::path(base_path())->env(['REFERRALFLOW_MODE' => 'production', 'REFERRALFLOW_PRODUCTION_ACK' => config('referralflow.ack_phrase'), 'APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'APP_KEY' => config('app.key')])->run('php artisan golive:gate');
    expect($ok->output())->toContain('UNMET')->and($ok->exitCode())->not->toBe(0); // acknowledged, but the gate still reports unmet items
    $app = Process::path(base_path())->env(['APP_ENV' => 'production', 'REFERRALFLOW_PRODUCTION_ACK' => '', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'APP_KEY' => config('app.key')])->run('php artisan about');
    expect($app->output().$app->errorOutput())->toContain('refuses to start');
});

it('detects production flags and acknowledgement in code', function () {
    config(['referralflow.mode' => 'staging', 'app.env' => 'testing']);
    expect(ProductionGuard::flagged())->toBeFalse();
    ProductionGuard::assertBootable();
    config(['referralflow.mode' => 'production', 'referralflow.production_ack' => 'wrong']);
    expect(ProductionGuard::flagged())->toBeTrue()->and(ProductionGuard::acknowledged())->toBeFalse();
    expect(fn () => ProductionGuard::assertBootable())->toThrow(RuntimeException::class, 'refuses to start');
    config(['referralflow.production_ack' => config('referralflow.ack_phrase')]);
    ProductionGuard::assertBootable();
    config(['referralflow.mode' => 'staging', 'app.env' => 'production', 'referralflow.production_ack' => null]);
    expect(fn () => ProductionGuard::assertBootable())->toThrow(RuntimeException::class);
});

it('shows a permanent not-cleared banner once production is flagged and acknowledged', function () {
    config(['referralflow.mode' => 'production', 'referralflow.production_ack' => config('referralflow.ack_phrase')]);
    $this->get('/login')->assertSee('NOT CLEARED FOR REAL PATIENT DATA');
});

it('go-live gate lists unmet legal, security and clinical prerequisites and exits non-zero', function () {
    $items = GoLiveGate::items();
    $areas = array_unique(array_column($items, 'area'));
    expect($areas)->toContain('Legal')->toContain('Security')->toContain('Clinical')->toContain('Organisation');
    $unmet = array_column(GoLiveGate::unmet(), 'id');
    foreach (['legal.counsel', 'legal.odpc', 'legal.dpia', 'org.agreements', 'org.moh', 'sec.pentest', 'clin.safety'] as $id) {
        expect($unmet)->toContain($id);
    }
    $this->artisan('golive:gate')->assertFailed();
});

it('labels all patients and data as synthetic and refuses patient creation without the confirmation', function () {
    $f = facility();
    $t = facility();
    $u = user(Role::Clinician, $f);
    activeSchema();
    signedIn($this, $u)->get('/referrals/new')->assertSee('Synthetic demonstration data')->assertSee('made-up people');
    $payload = ['patient_mode' => 'new', 'full_name' => 'X Y', 'date_of_birth' => '1990-01-01', 'sex' => 'male', 'priority' => 'routine', 'reason_code' => 'other', 'reason_text' => 'r', 'receiving_facility_id' => $t->id, 'action' => 'draft'];
    signedIn($this, $u)->post('/referrals', $payload)->assertSessionHasErrors('synthetic_confirm');
    signedIn($this, $u)->post('/referrals', $payload + ['synthetic_confirm' => '1'])->assertSessionHasNoErrors();
    expect(Patient::query()->withoutGlobalScopes()->first()->is_synthetic)->toBeTrue()->and(Referral::query()->first()->is_synthetic)->toBeTrue();
});

it('exposes a health endpoint with no patient data', function () {
    $res = $this->get('/health')->assertOk();
    expect(array_keys($res->json()))->toBe(['status', 'database', 'mode', 'synthetic_data_only']);
    $this->get('/up')->assertOk();
});

// ---- offline behaviour: no patient data on the device ----

it('ships a service worker that caches only the static shell and the offline page', function () {
    $sw = file_get_contents(public_path('sw.js'));
    expect($sw)->toContain("'/offline'")->toContain('/build/assets/')->toContain('req.method !== \'GET\'');
    // no authenticated route or API path is ever precached or cached at runtime
    foreach (['/referrals', '/api/', '/inbox', '/dashboard', '/documents', '/admin'] as $p) {
        expect($sw)->not->toContain("'$p");
    }
    // runtime caching only for build assets and the SHELL list; navigations are network-only with offline fallback
    expect($sw)->toContain("req.mode === 'navigate'")->toContain('caches.match(\'/offline\')');
});

it('stores no patient data in browser storage: only non-PHI draft fields', function () {
    $js = file_get_contents(resource_path('js/app.js'));
    preg_match_all("/store\.set\('(\w+)'/", $js, $m);
    expect(array_unique($m[1]))->toEqualCanonicalizing(['rf_draft', 'rf_dir']);
    // the draft writes only these keys
    expect($js)->toContain('priority:')->toContain('reason_code:')->toContain('receiving_facility_id:');
    foreach (['full_name', 'date_of_birth', 'identifier', 'clinical_note', 'reason_text', 'localStorage.setItem(\'patient'] as $forbidden) {
        $draftBlock = substr($js, (int) strpos($js, 'const saveDraft'), 500);
        expect($draftBlock)->not->toContain($forbidden);
    }
    expect($js)->toContain("store.del('rf_draft')"); // cleared on sign-out
});

it('serves the offline page and manifest without authentication or patient data', function () {
    $this->get('/offline')->assertOk()->assertSee('Patient details cannot be saved offline')->assertSee('Draft without patient details');
    expect(json_decode(file_get_contents(public_path('manifest.webmanifest')), true)['name'])->toContain('synthetic');
    $d = signedIn($this, user(Role::Clinician))->get('/api/v1/directory')->assertOk()->json();
    expect(array_keys($d[0] ?? ['id' => 1, 'name' => 1, 'type' => 1, 'phone' => 1, 'state' => 1]))->toBe(['id', 'name', 'type', 'phone', 'state']);
});

// ---- Swahili copy ----

it('has a Kiswahili entry for every translatable string used in the views', function () {
    $sw = json_decode(file_get_contents(lang_path('sw.json')), true);
    $missing = [];
    $found = 0;
    foreach (File::allFiles(resource_path('views')) as $file) {
        preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $m);
        foreach ($m[1] as $key) {
            $key = stripslashes($key);
            $found++;
            if (! array_key_exists($key, $sw)) {
                $missing[$key] = true;
            }
        }
    }
    expect($found)->toBeGreaterThan(300);
    expect(array_keys($missing))->toBe([]);
});

it('also translates controller messages, config lists and notification text', function () {
    $sw = json_decode(file_get_contents(lang_path('sw.json')), true);
    $keys = [];
    foreach (['reason_codes', 'decline_reasons', 'cancel_reasons', 'outcomes', 'consent_bases', 'identifier_types', 'reveal_reasons'] as $list) {
        $keys = array_merge($keys, array_values(config("referralflow.$list")));
    }
    $keys = array_merge($keys, array_values(Notifier::TEXT));
    foreach (File::allFiles(app_path('Http/Controllers')) as $f) {
        preg_match_all("/__\('((?:[^'\\\\]|\\\\.)*)'\)/", $f->getContents(), $m);
        $keys = array_merge($keys, array_map('stripslashes', $m[1]));
    }
    $missing = array_values(array_filter(array_unique($keys), fn ($k) => ! isset($sw[$k])));
    expect($missing)->toBe([]);
});

it('renders the interface in Kiswahili when chosen, for guests and signed-in users', function () {
    $this->post('/locale', ['locale' => 'sw']);
    $this->get('/login')->assertSee('Ingia')->assertSee('Data za maonyesho ya kubuni');
    $u = user(Role::Clinician);
    signedIn($this, $u)->post('/settings', ['locale' => 'sw']);
    expect($u->fresh()->locale)->toBe('sw');
    signedIn($this, $u->fresh())->get('/')->assertSee('Rufaa mpya')->assertSee('Habari');
    $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
});
