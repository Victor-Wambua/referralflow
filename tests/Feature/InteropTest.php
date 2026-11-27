<?php

use App\Directory\DirectoryImporter;
use App\Domain\AttachmentService;
use App\Domain\ReferralService;
use App\Enums\Role;
use App\Interop\FhirExporter;
use App\Interop\FhirSchemas;
use App\Models\Facility;
use App\Models\FacilityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->A = facility(['name' => 'From HC', 'phone' => '+254700111222']);
    $this->B = facility(['name' => 'To Hospital']);
    $this->clin = user(Role::Clinician, $this->A);
    $this->coord = user(Role::Coordinator, $this->B);
});

function pngBytes(): string
{
    $i = imagecreatetruecolor(5, 5);
    ob_start();
    imagepng($i);

    return ob_get_clean();
}

function resources(array $bundle, string $type): array
{
    return array_values(array_filter(array_column($bundle['entry'], 'resource'), fn ($r) => $r['resourceType'] === $type));
}

it('exports a FHIR-shaped bundle with every promised resource type that validates against the authored schemas', function () {
    $r = acceptedReferral($this->clin, $this->coord, ['reason_text' => 'Needs review', 'clinical_note' => 'Note text']);
    ReferralService::postMessage($this->coord, $r, 'Message text');
    $this->be($this->clin);
    AttachmentService::store($this->clin, $r, UploadedFile::fake()->createWithContent('x.png', pngBytes()));
    $bundle = FhirExporter::bundle($this->clin, $r->fresh());

    expect($bundle['resourceType'])->toBe('Bundle')->and($bundle['type'])->toBe('collection');
    $types = array_count_values(array_column(array_column($bundle['entry'], 'resource'), 'resourceType'));
    expect($types)->toMatchArray(['ServiceRequest' => 1, 'Patient' => 1, 'Organization' => 2, 'Location' => 2, 'Task' => 1, 'Communication' => 1, 'DocumentReference' => 1]);
    expect(FhirSchemas::validateBundle($bundle))->toBe([]);

    $sr = resources($bundle, 'ServiceRequest')[0];
    expect($sr['priority'])->toBe('urgent')->and($sr['intent'])->toBe('order')->and($sr['status'])->toBe('active')
        ->and($sr['requester']['reference'])->toBe('Organization/'.$this->A->id)->and($sr['performer'][0]['reference'])->toBe('Organization/'.$this->B->id)
        ->and($sr['note'][0]['text'])->toBe('Note text');
    $p = resources($bundle, 'Patient')[0];
    expect($p['name'][0]['text'])->toStartWith('Synthetic Person')->and($p['identifier'][0]['system'])->toBe('urn:referralflow:patient-identifier:facility_record');
    $doc = resources($bundle, 'DocumentReference')[0];
    expect($doc['content'][0]['attachment']['contentType'])->toBe('image/png')->and($doc['content'][0]['attachment']['title'])->toBe('Attachment')
        ->and(strlen((string) base64_decode($doc['content'][0]['attachment']['hash'], true)))->toBe(20); // SHA-1, as FHIR requires
    $com = resources($bundle, 'Communication')[0];
    expect($com['payload'][0]['contentString'])->toBe('Message text')->and($com['sender']['reference'])->toBe('Organization/'.$this->B->id);
    $org = resources($bundle, 'Organization')[0];
    expect($org['identifier'][0]['system'])->toContain('synthetic');
});

it('labels every resource and the bundle as FHIR-shaped and unvalidated', function () {
    $r = sentReferral($this->clin, $this->B);
    $bundle = FhirExporter::bundle($this->clin, $r);
    expect($bundle['meta']['tag'][0]['display'])->toBe('FHIR-shaped, not validated against any national HIE; no access to national systems exists or is claimed');
    foreach ($bundle['entry'] as $e) {
        expect($e['resource']['meta']['tag'][0]['code'])->toBe('fhir-shaped-unvalidated');
    }
    signedIn($this, $this->clin)->get("/api/v1/referrals/{$r->id}/fhir")->assertOk()
        ->assertHeader('X-Interop-Notice', FhirExporter::NOTICE)->assertJsonPath('resourceType', 'Bundle');
});

it('maps all nine statuses and three priorities to FHIR values', function () {
    $map = [
        'created' => ['draft', 'draft'], 'sent' => ['active', 'requested'], 'received' => ['active', 'received'], 'accepted' => ['active', 'accepted'],
        'scheduled' => ['active', 'ready'], 'attended' => ['active', 'in-progress'], 'completed' => ['completed', 'completed'],
        'declined' => ['revoked', 'rejected'], 'cancelled' => ['revoked', 'cancelled'],
    ];
    expect(array_keys($map))->toBe(config('referralflow.statuses'));
    $r = draftReferral($this->clin, $this->B);
    foreach ($map as $status => [$sr, $task]) {
        $r->forceFill(['status' => $status])->save();
        $b = FhirExporter::bundle($this->clin, $r->fresh());
        expect(resources($b, 'ServiceRequest')[0]['status'])->toBe($sr, $status)->and(resources($b, 'Task')[0]['status'])->toBe($task, $status);
        expect(resources($b, 'Task')[0]['businessStatus']['text'])->toBe($status);
        expect(FhirSchemas::validateBundle($b))->toBe([], $status);
    }
    foreach (['emergency' => 'stat', 'urgent' => 'urgent', 'routine' => 'routine'] as $our => $fhir) {
        $r->forceFill(['priority' => $our])->save();
        expect(resources(FhirExporter::bundle($this->clin, $r->fresh()), 'ServiceRequest')[0]['priority'])->toBe($fhir);
    }
});

it('shares only the minimum before acceptance: no name, birth date, identifier, note or documents', function () {
    $r = sentReferral($this->clin, $this->B);
    $b = FhirExporter::bundle($this->coord, $r);
    $p = resources($b, 'Patient')[0];
    expect($p)->not->toHaveKey('name')->not->toHaveKey('birthDate')->not->toHaveKey('identifier')->and($p['gender'])->toBe('female');
    expect(resources($b, 'ServiceRequest')[0])->not->toHaveKey('note');
    expect(resources($b, 'DocumentReference'))->toBe([]);
    expect(FhirSchemas::validateBundle($b))->toBe([]);
    $this->assertStringNotContainsString('Synthetic Person', json_encode($b));
    $this->assertStringNotContainsString('Synthetic clinical note', json_encode($b));
    // outsiders and tombstoned views cannot export
    expect(fn () => FhirExporter::bundle(user(Role::Clinician), $r))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    signedIn($this, user(Role::Clinician))->get("/api/v1/referrals/{$r->id}/fhir")->assertNotFound();
});

it('the schemas reject malformed resources (they are a real check, not a rubber stamp)', function () {
    $r = acceptedReferral($this->clin, $this->coord);
    $good = FhirExporter::bundle($this->clin, $r->fresh());
    $sr = resources($good, 'ServiceRequest')[0];
    $cases = [
        'bad status' => ['ServiceRequest', ['status' => 'bogus']],
        'bad intent' => ['ServiceRequest', ['intent' => 'maybe']],
        'bad priority' => ['ServiceRequest', ['priority' => 'emergency']],
        'no subject' => ['ServiceRequest', ['subject' => null]],
        'bad reference' => ['ServiceRequest', ['requester' => ['reference' => 'not a reference']]],
        'no label' => ['ServiceRequest', ['meta' => null]],
    ];
    foreach ($cases as $name => [$type, $patch]) {
        $res = $sr;
        foreach ($patch as $k => $v) {
            $v === null ? ($res = array_diff_key($res, [$k => 1])) : ($res[$k] = $v);
        }
        expect(FhirSchemas::validate($res))->not->toBe([], $name);
    }
    $task = resources($good, 'Task')[0];
    $task['status'] = 'done';
    expect(FhirSchemas::validate($task))->not->toBe([]);
    $loc = resources($good, 'Location')[0];
    $loc['address']['country'] = 'UG';
    expect(FhirSchemas::validate($loc))->not->toBe([]);
    expect(FhirSchemas::validate(['resourceType' => 'Banana']))->toBe(['Unknown resourceType']);
    $emptyBundle = $good;
    $emptyBundle['entry'] = [];
    expect(FhirSchemas::validateBundle($emptyBundle))->not->toBe([]);
});

// ---- directory import (KMHFL-style CSV, synthetic fixtures) ----

it('imports the synthetic KMHFL-style fixture: facilities with unverified services, idempotently', function () {
    $r = DirectoryImporter::import(database_path('fixtures/facilities-synthetic.csv'));
    expect($r['errors'])->toBe([])->and($r['created'])->toBe(10)->and($r['updated'])->toBe(0);
    $f = Facility::query()->where('mfl_code', '900002')->first();
    expect($f->name)->toBe('Ziwani Sub-County Hospital')->and($f->type)->toBe('sub_county_hospital')->and($f->keph_level)->toBe(4)->and($f->hours)->toBe('24 hours')
        ->and($f->info_verified_at)->toBeNull()->and($f->verificationState())->toBe('unverified')->and($f->data_origin)->toBe('synthetic test data');
    expect($f->services()->count())->toBe(5)->and($f->services()->whereNotNull('verified_at')->count())->toBe(0);
    expect(Facility::query()->where('mfl_code', '900008')->first()->type)->toBe('referral_hospital');
    // re-import: updates, never creates duplicates, and never wipes verification
    $f->forceFill(['info_verified_at' => now()])->save();
    FacilityService::query()->where('facility_id', $f->id)->update(['verified_at' => now()]);
    $r2 = DirectoryImporter::import(database_path('fixtures/facilities-synthetic.csv'));
    expect($r2['created'])->toBe(0)->and($r2['updated'])->toBe(10);
    expect(Facility::query()->where('mfl_code', '900002')->count())->toBe(1)->and($f->fresh()->info_verified_at)->not->toBeNull()
        ->and($f->services()->whereNotNull('verified_at')->count())->toBe(5)->and($f->services()->count())->toBe(5);
});

it('reports row-level errors and imports the good rows; a dry run changes nothing', function () {
    $csv = tempnam(sys_get_temp_dir(), 'kmhfl');
    file_put_contents($csv, implode("\n", [
        implode(',', DirectoryImporter::COLUMNS),
        '700001,Good One,Dispensary,2,County A,,,Public,Operational,+254700000001,ok@example.invalid,Mon-Fri,Outpatient|Lab',
        'ABC,Bad Code,Dispensary,2,County A,,,Public,Operational,,,,',
        '700003,,Dispensary,2,County A,,,Public,Operational,,,,',
        '700004,Bad Email,Dispensary,2,County A,,,Public,Operational,,not-an-email,,',
        '700005,Bad Level,Dispensary,9,County A,,,Public,Operational,,,,',
        '700006,Too,Few,Columns',
        '700001,Duplicate,Dispensary,2,County A,,,Public,Operational,,,,',
        '700007,Closed One,Health Centre,3,County B,,,Public,Closed,,,,',
    ]));
    $dry = DirectoryImporter::import($csv, true);
    expect($dry['dry_run'])->toBeTrue()->and($dry['created'])->toBe(2)->and(count($dry['errors']))->toBe(6);
    expect(Facility::query()->where('mfl_code', 'like', '7000%')->count())->toBe(0);
    $res = DirectoryImporter::import($csv);
    expect($res['created'])->toBe(2)->and(array_column($res['errors'], 'line'))->toBe([3, 4, 5, 6, 7, 8]);
    expect(Facility::query()->where('mfl_code', '700007')->first()->is_active)->toBeFalse();
    expect(Facility::query()->where('mfl_code', '700001')->first()->services()->count())->toBe(2);
    unlink($csv);
});

it('rejects files with missing columns and imports through the admin UI and the command', function () {
    $bad = tempnam(sys_get_temp_dir(), 'kmhfl');
    file_put_contents($bad, "code,name\n1,x\n");
    $r = DirectoryImporter::import($bad);
    expect($r['errors'][0]['message'])->toContain('Missing columns')->and($r['created'])->toBe(0);
    unlink($bad);
    $admin = user(Role::PlatformAdmin);
    $up = UploadedFile::fake()->createWithContent('f.csv', file_get_contents(database_path('fixtures/facilities-synthetic.csv')));
    signedIn($this, user(Role::FacilityAdmin))->post('/admin/facilities/import', ['csv' => $up])->assertForbidden();
    signedIn($this, $admin)->post('/admin/facilities/import', ['csv' => $up, 'dry_run' => '1'])->assertSessionHas('import');
    expect(Facility::query()->where('mfl_code', '900001')->exists())->toBeFalse();
    signedIn($this, $admin)->post('/admin/facilities/import', ['csv' => UploadedFile::fake()->createWithContent('f.csv', file_get_contents(database_path('fixtures/facilities-synthetic.csv')))])->assertSessionHas('import');
    expect(Facility::query()->where('mfl_code', '900001')->exists())->toBeTrue();
    signedIn($this, $admin)->post('/admin/facilities/import', ['csv' => UploadedFile::fake()->create('f.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('csv');
    $this->artisan('directory:import', ['file' => database_path('fixtures/facilities-synthetic.csv'), '--dry-run' => true])->assertSuccessful();
});

it('searches the directory and flags verification state with thresholds', function () {
    DirectoryImporter::import(database_path('fixtures/facilities-synthetic.csv'));
    $u = user(Role::Clinician);
    signedIn($this, $u)->get('/directory?q=Oncology')->assertSee('Bonde County Referral Hospital')->assertDontSee('Jua Dispensary');
    signedIn($this, $u)->get('/directory?type=dispensary')->assertSee('Jua Dispensary')->assertDontSee('Bonde County');
    signedIn($this, $u)->get('/directory?q=zzzz')->assertSee('No facilities match');
    $f = Facility::query()->where('mfl_code', '900001')->first();
    foreach ([[null, 'unverified'], [now()->subDays(2), 'verified'], [now()->subDays(10), 'due'], [now()->subDays(45), 'stale']] as [$at, $state]) {
        $f->forceFill(['info_verified_at' => $at])->save();
        expect($f->fresh()->verificationState())->toBe($state);
        signedIn($this, $u)->get('/directory?verification='.$state)->assertSee('Mto Mkuu Health Centre');
    }
    signedIn($this, $u)->get("/directory/{$f->id}")->assertSee('This information is stale');
    // a coordinator of that facility can verify; the timestamp and verifier are recorded
    $c = user(Role::Coordinator, $f);
    signedIn($this, $c)->post("/directory/{$f->id}/verify", ['available' => [(string) $f->services()->first()->id]])->assertSessionHasNoErrors();
    $f->refresh();
    expect($f->verificationState())->toBe('verified')->and($f->info_verified_by)->toBe($c->id)->and($f->services()->where('available', false)->count())->toBe($f->services()->count() - 1);
    signedIn($this, user(Role::Coordinator))->post("/directory/{$f->id}/verify")->assertForbidden(); // another facility's coordinator
});
