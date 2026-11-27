<?php

namespace App\Http\Controllers\Admin;

use App\Directory\Verification;
use App\Domain\FormSchemaValidator;
use App\Domain\RetentionService;
use App\Http\Controllers\Controller;
use App\Models\FormSchema;
use App\Models\LegalHold;
use App\Models\Setting;
use App\Security\AuditLog;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const RETENTION = ['closed_referral_days', 'message_days', 'attachment_days', 'draft_days'];

    public function show()
    {
        return view('admin.retention', [
            'values' => collect(self::RETENTION)->mapWithKeys(fn ($k) => [$k => RetentionService::days($k)])->all(),
            'due' => Verification::dueDays(), 'stale' => Verification::staleDays(),
            'holds' => LegalHold::query()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $rules = collect(self::RETENTION)->mapWithKeys(fn ($k) => [$k => ['required', 'integer', 'between:1,36500']])->all();
        $d = $request->validate($rules + ['due_days' => ['required', 'integer', 'between:1,365'], 'stale_days' => ['required', 'integer', 'between:2,730', 'gt:due_days']]);
        foreach (self::RETENTION as $k) {
            Setting::put('retention.'.$k, (int) $d[$k]);
        }
        Setting::put('verification.due_days', (int) $d['due_days']);
        Setting::put('verification.stale_days', (int) $d['stale_days']);
        AuditLog::record('settings.update', 'settings', 'retention', collect($d)->all());

        return back()->with('status', __('Settings saved. These periods are placeholders: confirm with counsel.'));
    }

    public function purge(Request $request)
    {
        $dry = ! $request->boolean('confirm');
        $res = RetentionService::purge($dry);
        AuditLog::record('retention.run', 'settings', 'retention', $res + ['dry_run' => $dry]);

        return back()->with('purge', $res + ['dry_run' => $dry]);
    }

    public function hold(Request $request)
    {
        $d = $request->validate(['referral_id' => ['nullable', 'string', 'size:26', 'required_without:patient_id'], 'patient_id' => ['nullable', 'integer', 'required_without:referral_id'], 'reason' => ['required', 'string', 'max:500']]);
        $h = LegalHold::query()->create(['referral_id' => $d['referral_id'] ?? null, 'patient_id' => $d['patient_id'] ?? null, 'reason' => $d['reason'], 'placed_by' => $request->user()->id, 'placed_at' => now()]);
        AuditLog::record('legal_hold.place', 'legal_hold', $h->id, [], $d['reason']);

        return back()->with('status', __('Legal hold placed.'));
    }

    public function release(Request $request, LegalHold $hold)
    {
        $hold->forceFill(['released_at' => now(), 'released_by' => $request->user()->id])->save();
        AuditLog::record('legal_hold.release', 'legal_hold', $hold->id);

        return back()->with('status', __('Legal hold released.'));
    }

    public function forms()
    {
        return view('admin.forms', ['schemas' => FormSchema::query()->orderBy('key')->orderByDesc('version')->get(), 'types' => FormSchemaValidator::TYPES]);
    }

    public function storeForm(Request $request)
    {
        $d = $request->validate(['key' => ['required', 'regex:/^[a-z0-9\-]{3,60}$/'], 'name' => ['required', 'string', 'max:120'], 'fields' => ['required', 'string', 'max:20000']]);
        $fields = json_decode($d['fields'], true);
        $errors = FormSchemaValidator::definitionErrors($fields);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors = ['Fields must be valid JSON.'];
        }
        if ($errors) {
            return back()->withErrors(['fields' => implode(' ', $errors)])->withInput();
        }
        $version = (int) FormSchema::query()->where('key', $d['key'])->max('version') + 1;
        $s = FormSchema::query()->create(['key' => $d['key'], 'version' => $version, 'name' => $d['name'], 'status' => 'draft', 'fields' => $fields, 'created_by' => $request->user()->id]);
        AuditLog::record('form_schema.create', 'form_schema', $s->id, ['key' => $s->key, 'version' => $version]);

        return back()->with('status', __('New version saved as draft.'));
    }

    public function activateForm(Request $request, FormSchema $schema)
    {
        FormSchema::query()->where('status', 'active')->update(['status' => 'retired']);
        $schema->forceFill(['status' => 'active', 'activated_at' => now()])->save();
        AuditLog::record('form_schema.activate', 'form_schema', $schema->id, ['key' => $schema->key, 'version' => $schema->version]);

        return back()->with('status', __('Form version activated. Existing referrals keep the version they were written with.'));
    }
}
