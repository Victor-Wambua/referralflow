<?php

namespace App\Http\Controllers;

use App\Domain\AttachmentService;
use App\Domain\DuplicatePrompt;
use App\Domain\FormSchemaValidator;
use App\Domain\PatientService;
use App\Domain\ReferralService;
use App\Domain\ReferralView;
use App\Domain\StatusMachine;
use App\Interop\FhirExporter;
use App\Models\Attachment;
use App\Models\BreakGlassGrant;
use App\Models\Facility;
use App\Models\FormSchema;
use App\Models\Patient;
use App\Models\Referral;
use App\Security\AuditLog;
use App\Security\Masker;
use App\Security\ReferralAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReferralController extends Controller
{
    public function index(Request $request)
    {
        $q = Referral::query()->with(['referringFacility:id,name', 'receivingFacility:id,name']);
        if ($s = trim((string) $request->query('q'))) {
            $codes = collect(config('referralflow.reason_codes'))->filter(fn ($l) => stripos($l, $s) !== false)->keys()->all();
            $q->where(fn ($w) => $w->where('id', 'like', '%'.strtolower($s).'%')->orWhereIn('reason_code', $codes));
        }
        if (in_array($request->query('status'), config('referralflow.statuses'), true)) {
            $q->where('status', $request->query('status'));
        }
        $q->orderByRaw("case priority when 'emergency' then 0 when 'urgent' then 1 else 2 end")->orderByDesc('created_at');
        $page = $q->paginate(15)->withQueryString();
        AuditLog::record('referral.list', 'referral', null, ['count' => $page->count(), 'page' => $page->currentPage()]);

        return view('referrals.index', ['page' => $page, 'user' => $request->user()]);
    }

    public function inbox(Request $request)
    {
        $fid = $request->user()->facility_id;
        $base = Referral::query()->where('receiving_facility_id', $fid)->whereNotNull('sent_at')->with('referringFacility:id,name');
        $buckets = [
            'need_response' => ['label' => 'Need response', 'statuses' => ['sent', 'received']],
            'in_progress' => ['label' => 'Accepted and scheduled', 'statuses' => ['accepted', 'scheduled']],
            'awaiting_summary' => ['label' => 'Awaiting summary', 'statuses' => ['attended']],
        ];
        $tab = array_key_exists((string) $request->query('tab'), $buckets) ? $request->query('tab') : 'need_response';
        $counts = [];
        foreach ($buckets as $k => $b) {
            $counts[$k] = (clone $base)->whereIn('status', $b['statuses'])->count();
        }
        $items = (clone $base)->whereIn('status', $buckets[$tab]['statuses'])
            ->orderByRaw("case priority when 'emergency' then 0 when 'urgent' then 1 else 2 end")->orderBy('sent_at')->limit(50)->get();
        AuditLog::record('referral.inbox', 'referral', null, ['count' => $items->count(), 'tab' => $tab]);

        return view('referrals.inbox', compact('buckets', 'tab', 'counts', 'items'));
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $patients = Patient::query()->orderByDesc('id')->limit(12)->get();
        AuditLog::record('patient.list', 'patient', null, ['count' => $patients->count()]);

        return view('referrals.new', [
            'patients' => $patients->map(fn ($p) => ['id' => $p->id, 'initials' => Masker::initials($p->full_name), 'sex' => $p->sex, 'age' => $p->ageYears()]),
            'facilities' => Facility::query()->where('is_active', true)->where('id', '!=', $user->facility_id)->with('services')->orderBy('name')->get(),
            'schema' => FormSchema::active(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $schema = FormSchema::active();
        $sending = $request->input('action') === 'send';
        $rules = [
            'patient_mode' => ['required', 'in:existing,new'],
            'existing_patient_id' => ['required_if:patient_mode,existing', 'nullable', 'integer'],
            'full_name' => ['required_if:patient_mode,new', 'nullable', 'string', 'max:120'],
            'date_of_birth' => ['required_if:patient_mode,new', 'nullable', 'date', 'before_or_equal:today', 'after:1900-01-01'],
            'sex' => ['required_if:patient_mode,new', 'nullable', 'in:female,male,other'],
            'identifier_type' => ['nullable', 'required_with:identifier', Rule::in(array_keys(config('referralflow.identifier_types')))],
            'identifier' => ['nullable', 'string', 'max:40'],
            'synthetic_confirm' => ['nullable', 'boolean'],
            'dup_decision' => ['nullable', 'in:use,different'],
            'dup_reason' => ['nullable', 'string', 'max:300'],
            'priority' => ['required', Rule::in(config('referralflow.priorities'))],
            'reason_code' => ['required', Rule::in(array_keys(config('referralflow.reason_codes')))],
            'reason_text' => ['required', 'string', 'max:240'],
            'include_note' => ['nullable', 'boolean'],
            'clinical_note' => ['nullable', 'string', 'max:4000'],
            'receiving_facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'stale_confirmed' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:300'],
            'action' => ['required', 'in:send,draft'],
            'documents' => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file'],
        ];
        if ($sending) {
            $rules['consent_basis'] = ['required', Rule::in(array_keys(config('referralflow.consent_bases')))];
            $rules['consent_confirm'] = ['accepted'];
        }
        if ($schema) {
            $rules += FormSchemaValidator::rules($schema);
        }
        $d = $request->validate($rules);

        $stored = [];
        try {
            $referral = DB::transaction(function () use ($request, $user, $d, $schema, $sending, &$stored) {
                if ($d['patient_mode'] === 'existing') {
                    $patient = Patient::query()->findOrFail($d['existing_patient_id']);
                    AuditLog::record('patient.use_existing', 'patient', $patient->id);
                } else {
                    $m = PatientService::matches($d);
                    $match = $m['strong'] ?? $m['probable'];
                    $patient = null;
                    if ($match) {
                        $dec = $d['dup_decision'] ?? null;
                        if ($dec === 'use') {
                            $patient = $match;
                            AuditLog::record('patient.use_existing', 'patient', $patient->id, ['via' => 'duplicate_prompt']);
                        } elseif ($dec === 'different' && trim((string) ($d['dup_reason'] ?? '')) !== '') {
                            AuditLog::record('patient.duplicate_override', 'patient', $match->id, ['kind' => $m['strong'] ? 'strong' : 'probable'], $d['dup_reason']);
                        } else {
                            throw new DuplicatePrompt($match, $m['strong'] ? 'strong' : 'probable');
                        }
                    }
                    $patient ??= PatientService::create($user, $d + ['synthetic_confirm' => $request->boolean('synthetic_confirm')]);
                }
                $payload = $d + ['form_schema_id' => $schema?->id, 'form_data' => $schema ? ($request->input('form') ?: null) : null];
                $referral = ReferralService::create($user, $patient, $payload);
                foreach ($request->file('documents', []) as $file) {
                    $stored[] = AttachmentService::store($user, $referral, $file)->path;
                }
                if ($sending) {
                    $referral = ReferralService::send($user, $referral, ['confirmed' => true, 'basis' => $d['consent_basis'], 'witness' => $request->input('consent_witness')], $d['override_reason'] ?? null, $request->boolean('stale_confirmed'));
                }

                return $referral;
            });
        } catch (DuplicatePrompt $e) {
            return back()->withInput()->with('dup_match', ['kind' => $e->kind, 'initials' => Masker::initials($e->patient->full_name)]);
        } catch (\Throwable $e) {
            foreach ($stored as $p) {
                Storage::disk('local')->delete($p);
            }
            throw $e;
        }

        return redirect()->route('referrals.show', $referral->id)->with('status', $sending ? __('Referral sent.') : __('Draft saved.'));
    }

    public function show(Request $request, string $id)
    {
        $user = $request->user();
        [$r, $level] = ReferralAccess::locate($user, $id);
        if (! $r) {
            return response()->view('referrals.locked', ['id' => $id], 403);
        }
        if ($level === ReferralAccess::MINIMAL && $r->status === 'sent' && ReferralAccess::side($user, $r) === 'receiver') {
            ReferralService::markReceived($user, $r);
            $r->refresh();
        }
        $bg = $level === ReferralAccess::BREAKGLASS;
        $revealed = $this->revealActive($request, $r->id) && in_array($level, [ReferralAccess::FULL, ReferralAccess::BREAKGLASS], true);
        AuditLog::record('referral.view', 'referral', $r->id, ['level' => $level], null, $bg ? 'break_glass' : null);
        $tab = in_array($request->query('tab'), ['messages', 'history'], true) ? $request->query('tab') : 'overview';
        $view = ReferralView::build($user, $r, $level, $revealed);
        $messages = [];
        if ($tab === 'messages' && in_array($level, [ReferralAccess::FULL, ReferralAccess::MINIMAL, ReferralAccess::BREAKGLASS], true)) {
            $messages = $r->messages()->with('author:id,name,role', 'facility:id,name')->get();
            AuditLog::record('message.list', 'referral', $r->id, ['count' => $messages->count()], null, $bg ? 'break_glass' : null);
        }
        $grant = $bg ? BreakGlassGrant::query()->active()->where('user_id', $user->id)->where('referral_id', $r->id)->first() : null;

        return view('referrals.show', [
            'v' => $view,
            'sent_at' => $r->sent_at,
            'tab' => $tab,
            'messages' => $messages,
            'actions' => StatusMachine::available($user, $r),
            'can_message' => in_array($level, [ReferralAccess::FULL, ReferralAccess::MINIMAL], true) && ! $r->purged_at,
            'can_attach' => $view['side'] === 'referrer' && $level === ReferralAccess::FULL && ! $r->isTerminal() && $user->isClinical(),
            'can_withdraw' => $view['side'] === 'referrer' && $level === ReferralAccess::FULL && $r->consent && ! $r->consent->withdrawn_at && $user->isClinical(),
            'grant' => $grant,
            'declines' => config('referralflow.decline_reasons'),
            'cancels' => config('referralflow.cancel_reasons'),
            'outcomes' => config('referralflow.outcomes'),
            'reveal_reasons' => config('referralflow.reveal_reasons'),
            'consent_bases' => config('referralflow.consent_bases'),
            'facility_state' => $r->receivingFacility?->verificationState(),
        ]);
    }

    public function send(Request $request, string $id)
    {
        $r = $this->mine($request, $id);
        $request->validate(['consent_basis' => ['required', Rule::in(array_keys(config('referralflow.consent_bases')))], 'consent_confirm' => ['accepted'], 'override_reason' => ['nullable', 'string', 'max:300']]);
        ReferralService::send($request->user(), $r, ['confirmed' => true, 'basis' => $request->input('consent_basis'), 'witness' => $request->input('consent_witness')], $request->input('override_reason'), $request->boolean('stale_confirmed'));

        return $this->done($id, __('Referral sent.'));
    }

    public function accept(Request $request, string $id)
    {
        ReferralService::accept($request->user(), $this->mine($request, $id));

        return $this->done($id, __('Referral accepted.'));
    }

    public function decline(Request $request, string $id)
    {
        $this->mayMove($request, $id, 'declined');
        $d = $request->validate(['reason' => ['required', Rule::in(array_keys(config('referralflow.decline_reasons')))], 'note' => ['nullable', 'string', 'max:500']]);
        ReferralService::decline($request->user(), $this->mine($request, $id), $d['reason'], $d['note'] ?? null);

        return $this->done($id, __('Referral declined. The referring facility has been told.'));
    }

    public function schedule(Request $request, string $id)
    {
        $this->mayMove($request, $id, 'scheduled');
        $d = $request->validate(['appointment_at' => ['required', 'date']]);
        ReferralService::schedule($request->user(), $this->mine($request, $id), Carbon::parse($d['appointment_at']));

        return $this->done($id, __('Appointment saved.'));
    }

    public function attend(Request $request, string $id)
    {
        ReferralService::markAttended($request->user(), $this->mine($request, $id));

        return $this->done($id, __('Marked as attended.'));
    }

    public function complete(Request $request, string $id)
    {
        $this->mayMove($request, $id, 'completed');
        $d = $request->validate(['outcome' => ['required', Rule::in(array_keys(config('referralflow.outcomes')))], 'summary' => ['required', 'string', 'max:4000'], 'follow_up' => ['nullable', 'string', 'max:2000']]);
        ReferralService::complete($request->user(), $this->mine($request, $id), $d['outcome'], $d['summary'], $d['follow_up'] ?? null);

        return $this->done($id, __('Summary sent. The referral is complete.'));
    }

    public function cancel(Request $request, string $id)
    {
        $this->mayMove($request, $id, 'cancelled');
        $d = $request->validate(['reason' => ['required', Rule::in(array_keys(config('referralflow.cancel_reasons')))], 'note' => ['nullable', 'string', 'max:500']]);
        ReferralService::cancel($request->user(), $this->mine($request, $id), $d['reason'], $d['note'] ?? null);

        return $this->done($id, __('Referral cancelled.'));
    }

    public function withdraw(Request $request, string $id)
    {
        ReferralService::withdrawConsent($request->user(), $this->mine($request, $id));

        return $this->done($id, __('Consent withdrawal recorded. Sharing has stopped.'));
    }

    public function message(Request $request, string $id)
    {
        $d = $request->validate(['body' => ['required', 'string', 'max:2000'], 'kind' => ['nullable', 'in:message,info_request']]);
        ReferralService::postMessage($request->user(), $this->mine($request, $id), $d['body'], $d['kind'] ?? 'message');

        return redirect()->route('referrals.show', [$id, 'tab' => 'messages'])->with('status', __('Message sent.'));
    }

    public function reveal(Request $request, string $id)
    {
        [$r, $level] = $this->locate($request, $id);
        if (! in_array($level, [ReferralAccess::FULL, ReferralAccess::BREAKGLASS], true) || ! $r->patient_id) {
            throw new AuthorizationException('Identity is not shared at this stage.');
        }
        $d = $request->validate(['reason' => ['required', Rule::in(array_keys(config('referralflow.reveal_reasons')))]]);
        $request->session()->put('reveal.'.$r->id, time() + (int) config('referralflow.reveal_minutes') * 60);
        AuditLog::record('patient.reveal', 'referral', $r->id, ['level' => $level], $d['reason'], $level === ReferralAccess::BREAKGLASS ? 'break_glass' : null);

        return redirect()->route('referrals.show', $r->id);
    }

    public function hide(Request $request, string $id)
    {
        $request->session()->forget('reveal.'.$id);

        return redirect()->route('referrals.show', $id);
    }

    public function upload(Request $request, string $id)
    {
        $r = $this->mine($request, $id);
        if (ReferralAccess::side($request->user(), $r) !== 'referrer') {
            throw new AuthorizationException('Documents can be added by the referring facility.');
        }
        $request->validate(['document' => ['required', 'file']]);
        AttachmentService::store($request->user(), $r, $request->file('document'));

        return $this->done($id, __('Document added.'));
    }

    public function download(Request $request, string $id, string $doc)
    {
        [$r, $level] = $this->locate($request, $id);
        if (! in_array($level, [ReferralAccess::FULL, ReferralAccess::BREAKGLASS], true)) {
            throw new AuthorizationException('Documents are shared after acceptance.');
        }
        $a = Attachment::query()->where('referral_id', $r->id)->whereNull('purged_at')->findOrFail($doc);
        AuditLog::record('attachment.open', 'attachment', $a->id, ['referral' => $r->id], null, $level === ReferralAccess::BREAKGLASS ? 'break_glass' : null);

        return response(AttachmentService::read($a), 200, [
            'Content-Type' => $a->mime,
            'Content-Disposition' => 'attachment; filename="document-'.substr($a->id, -6).'.'.config('referralflow.uploads.types.'.$a->mime).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function fhir(Request $request, string $id)
    {
        [$r, $level] = $this->locate($request, $id);
        $bundle = FhirExporter::bundle($request->user(), $r);
        AuditLog::record('fhir.export', 'referral', $r->id, ['level' => $level], null, $level === ReferralAccess::BREAKGLASS ? 'break_glass' : null);

        return response()->json($bundle, 200, ['X-Interop-Notice' => FhirExporter::NOTICE, 'Content-Type' => 'application/fhir+json'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** @return array{0:Referral,1:string} */
    private function locate(Request $request, string $id): array
    {
        [$r, $level] = ReferralAccess::locate($request->user(), $id);
        if (! $r) {
            abort(404);
        }

        return [$r, $level];
    }

    /** Authorise a status move BEFORE validating input, so unauthorised callers learn nothing about the form. */
    private function mayMove(Request $request, string $id, string $to): Referral
    {
        $r = $this->mine($request, $id);
        $probe = $r;
        if ($to === 'accepted' && $r->status === 'sent') {
            $probe = $r->replicate();
            $probe->id = $r->id;
            $probe->status = 'received';
        }
        if (! StatusMachine::may($request->user(), $probe, $to)) {
            // an illegal move for the current status is a 409 (handled elsewhere); a role/side problem is a 403
            if (StatusMachine::isLegal($probe->status, $to) || $to === 'accepted') {
                throw new AuthorizationException('You may not make this change.');
            }
        }

        return $r;
    }

    private function mine(Request $request, string $id): Referral
    {
        return $this->locate($request, $id)[0];
    }

    private function done(string $id, string $msg)
    {
        return redirect()->route('referrals.show', $id)->with('status', $msg);
    }

    private function revealActive(Request $request, string $id): bool
    {
        return (int) $request->session()->get('reveal.'.$id, 0) > time();
    }
}
