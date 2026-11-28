<?php

use App\Enums\Role;
use App\Security\AuditLog;

it('has a plain-language label and a Kiswahili string for every audit action code the app records', function () {
    $codes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            preg_match_all("/AuditLog::record\('([a-z_.]+)'/", file_get_contents($f->getPathname()), $m);
            $codes = array_merge($codes, $m[1]);
        }
    }
    $codes = array_unique(array_merge($codes, ['auth.login', 'auth.mfa_enrolled', 'auth.login_recovery', 'user.reactivate', 'user.suspend'])); // recorded through variables
    expect(count($codes))->toBeGreaterThan(50);
    $sw = json_decode(file_get_contents(lang_path('sw.json')), true);
    $missing = [];
    foreach ($codes as $c) {
        $label = config('referralflow.audit_labels')[$c] ?? null;
        if (! $label || ! isset($sw[$label])) {
            $missing[] = $c;
        }
    }
    expect($missing)->toBe([]);
});

it('shows the readable label and the exact code in the access log', function () {
    $u = user(Role::Clinician, facility());
    $t = signedIn($this, $u);
    AuditLog::record('referral.view', 'referral', '01HZZZZZZZZZZZZZZZZZZZZZZZ');
    $html = $t->get('/access')->assertOk()->getContent();
    expect($html)->toContain('class="stack"')->toContain('Referral viewed')->toContain('>referral.view<');
});
