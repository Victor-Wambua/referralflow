@extends('layouts.app')
@section('title', __('New referral'))
@section('content')
<h1>{{ __('New referral') }}</h1>
<div class="steps" id="stepbar" aria-hidden="true"><i class="on"></i><i></i><i></i><i></i></div>
<p class="mute" id="step-label" aria-live="polite"></p>

<div id="resume-draft" class="banner info" hidden><div>{{ __('You have a saved draft without patient details. Use it?') }}</div><button class="btn sm quiet" type="button" id="resume-yes">{{ __('Use draft') }}</button><button class="btn sm quiet" type="button" id="resume-no">{{ __('Discard') }}</button></div>

@if($errors->any())<div class="banner bad" role="alert"><div>{{ __('Please fix the highlighted items.') }}</div></div>@endif
@if(session('dup_match'))
@php($dm = session('dup_match'))
<div class="banner warn" role="alert"><div>
    <b>{{ $dm['kind'] === 'strong' ? __('A patient with this identifier already exists at your facility') : __('Possible duplicate patient') }}</b>
    <div>{{ __('Existing record') }}: <span class="masked">{{ $dm['initials'] }}</span></div>
    <input type="hidden" name="action" value="{{ old('action', 'send') }}" form="wizard">
    <div class="field" style="margin-top:8px"><label for="dup_reason">{{ __('If this is a different person, say why (required)') }}</label><input id="dup_reason" name="dup_reason" form="wizard" value="{{ old('dup_reason') }}"></div>
    <div class="btn-row"><button class="btn sm" form="wizard" name="dup_decision" value="use">{{ __('Use this patient') }}</button><button class="btn sm sec" form="wizard" name="dup_decision" value="different">{{ __('This is a different person') }}</button></div>
</div></div>
@endif

<form id="wizard" method="post" action="{{ route('referrals.store') }}" enctype="multipart/form-data" novalidate
      data-step-word="{{ __('Step') }}" data-step-of="{{ __('of') }}" data-yes="{{ __('Yes') }}" data-no="{{ __('No') }}" data-draft-saved="{{ __('Draft saved on this device without patient details.') }}" data-draft-resumed="{{ __('Draft restored.') }}">
@csrf
<input type="hidden" name="stale_confirmed" id="stale_confirmed" value="{{ old('stale_confirmed', '0') }}">

<section class="step">
    <h2 tabindex="-1">{{ __('1. Patient') }}</h2>
    <div class="hint-box">{{ __('Synthetic demonstration data only: use made-up people. Real patient data must never be entered.') }}</div>
    @php($mode = old('patient_mode', 'new'))
    <div class="seg" role="radiogroup" aria-label="{{ __('Patient') }}">
        <label class="opt"><input type="radio" name="patient_mode" value="new" @checked($mode==='new')><span><b>{{ __('New patient') }}</b></span></label>
        <label class="opt"><input type="radio" name="patient_mode" value="existing" @checked($mode==='existing')><span><b>{{ __('Patient already at my facility') }}</b></span></label>
    </div>
    <div id="pt-existing"><div data-need="radio" data-name="existing_patient_id" data-msg="{{ __('Choose a patient.') }}"></div>
        @forelse($patients as $p)
        <label class="opt"><input type="radio" name="existing_patient_id" value="{{ $p['id'] }}" @checked(old('existing_patient_id')==$p['id'])><span><span class="masked">{{ $p['initials'] }}</span> · {{ __($p['sex']) }} · {{ $p['age'] }} {{ __('years') }}</span></label>
        @empty
        <p class="mute">{{ __('No patients yet.') }}</p>
        @endforelse
        @error('existing_patient_id')<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    <div id="pt-new">
        <x-field name="full_name" :label="__('Full name (synthetic)')" data-need="text" :data-msg="__('Enter the patient name.')" />
        <div class="field"><label for="date_of_birth">{{ __('Date of birth') }}</label><input id="date_of_birth" name="date_of_birth" type="date" data-need="text" data-msg="{{ __('Enter the date of birth.') }}" value="{{ old('date_of_birth') }}" max="{{ now()->toDateString() }}">@error('date_of_birth')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="sex">{{ __('Sex') }}</label><select id="sex" name="sex" data-need="text" data-msg="{{ __('Choose sex.') }}"><option value="">{{ __('Choose') }}</option>@foreach(['female','male','other'] as $s)<option value="{{ $s }}" @selected(old('sex')===$s)>{{ __($s) }}</option>@endforeach</select>@error('sex')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="identifier_type">{{ __('Identifier type (optional)') }}</label><select id="identifier_type" name="identifier_type"><option value="">{{ __('None') }}</option>@foreach(config('referralflow.identifier_types') as $k => $l)<option value="{{ $k }}" @selected(old('identifier_type')===$k)>{{ __($l) }}</option>@endforeach</select>@error('identifier_type')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
        <x-field name="identifier" :label="__('Identifier (optional)')" :hint="__('Stored encrypted. Matching is only done inside your own facility.')" />
        <label class="opt"><input type="checkbox" name="synthetic_confirm" value="1" @checked(old('synthetic_confirm'))><span>{{ __('I confirm this is a synthetic (not real) person.') }}</span></label>
        @error('synthetic_confirm')<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="sticky-act"><button class="btn" type="button" data-next>{{ __('Continue') }}</button></div>
</section>

<section class="step">
    <h2 tabindex="-1">{{ __('2. Priority and reason') }}</h2>
    <fieldset style="border:0;padding:0;margin:0 0 18px"><legend style="font-weight:650;margin-bottom:6px">{{ __('Priority') }}</legend>
    <div class="seg" role="radiogroup">
        @foreach(['routine' => ['Routine', 'Within weeks'], 'urgent' => ['Urgent', 'Within 24 hours'], 'emergency' => ['Emergency', 'Now. Call ahead.']] as $k => [$l, $h])
        <label class="opt"><input type="radio" name="priority" value="{{ $k }}" data-label="{{ __($l) }}" @checked(old('priority', 'routine')===$k)><span><b>{{ __($l) }}</b><span class="hint" style="display:block">{{ __($h) }}</span></span></label>
        @endforeach
    </div>
    @error('priority')<div class="err" role="alert">{{ $message }}</div>@enderror</fieldset>
    <div class="field"><label for="reason_code">{{ __('Reason category') }}</label><select id="reason_code" name="reason_code" data-need="text" data-msg="{{ __('Choose a reason category.') }}"><option value="">{{ __('Choose') }}</option>@foreach(config('referralflow.reason_codes') as $k => $l)<option value="{{ $k }}" @selected(old('reason_code')===$k)>{{ __($l) }}</option>@endforeach</select>@error('reason_code')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="reason_text">{{ __('Reason in one line (up to 240 characters)') }}</label><textarea id="reason_text" name="reason_text" maxlength="240" style="min-height:80px" data-need="text" data-msg="{{ __('Write the reason in one line.') }}">{{ old('reason_text') }}</textarea><div class="hint">{{ __('Do not write names or ID numbers here.') }} <span id="reason-count" aria-live="off">0/240</span></div>@error('reason_text')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <label class="opt"><input type="checkbox" id="include_note" name="include_note" value="1" @checked(old('include_note'))><span><b>{{ __('Include a clinical note') }}</b><span class="hint" style="display:block">{{ __('Only shared with the receiving facility after they accept. Stored encrypted.') }}</span></span></label>
    <div class="field" id="note-box"><label for="clinical_note">{{ __('Clinical note') }}</label><textarea id="clinical_note" name="clinical_note" maxlength="4000">{{ old('clinical_note') }}</textarea>@error('clinical_note')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    @if($schema)
    <h3>{{ __('More details') }} <span class="tag-syn">{{ $schema->name }} v{{ $schema->version }}</span></h3>
    @foreach($schema->fields as $f)
    @php($n = 'form.'.$f['key'])
    <div class="field">
        @if($f['type'] === 'boolean')
        <label class="opt"><input type="checkbox" name="form[{{ $f['key'] }}]" value="1" @checked(old($n))><span>{{ __($f['label']) }}</span></label>
        @else
        <label for="f_{{ $f['key'] }}">{{ __($f['label']) }}</label>
        @if($f['type']==='textarea')<textarea id="f_{{ $f['key'] }}" name="form[{{ $f['key'] }}]" style="min-height:80px">{{ old($n) }}</textarea>
        @elseif($f['type']==='select')<select id="f_{{ $f['key'] }}" name="form[{{ $f['key'] }}]"><option value="">{{ __('Choose') }}</option>@foreach($f['options'] as $o)<option value="{{ $o['value'] }}" @selected(old($n)===$o['value'])>{{ __($o['label']) }}</option>@endforeach</select>
        @else<input id="f_{{ $f['key'] }}" name="form[{{ $f['key'] }}]" type="{{ $f['type'] === 'number' ? 'number' : ($f['type']==='date' ? 'date' : 'text') }}" value="{{ old($n) }}">@endif
        @endif
        @error($n)<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    @endforeach
    @endif
    <div class="field"><label for="documents">{{ __('Supporting documents (optional)') }}</label><input id="documents" name="documents[]" type="file" multiple accept="application/pdf,image/jpeg,image/png"><div class="hint">{{ __('PDF, JPEG or PNG, up to 5 MB each, up to 5 files. Do not put names in file names. Shared after the receiving facility accepts.') }}</div>@error('documents')<div class="err" role="alert">{{ $message }}</div>@enderror @error('documents.*')<div class="err" role="alert">{{ $message }}</div>@enderror @error('document')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="sticky-act"><button class="btn quiet" type="button" data-prev>{{ __('Back') }}</button><button class="btn" type="button" data-next>{{ __('Continue') }}</button></div>
</section>

<section class="step">
    <h2 tabindex="-1">{{ __('3. Where to send it') }}</h2>
    <div data-need="radio" data-name="receiving_facility_id" data-msg="{{ __('Choose a facility.') }}"></div>
    @error('receiving_facility_id')<div class="err" role="alert">{{ $message }}</div>@enderror
    @error('stale_confirmed')<div class="err" role="alert">{{ $message }}</div>@enderror
    <div class="field"><label for="fac-filter">{{ __('Filter facilities') }}</label><input id="fac-filter" type="search" autocomplete="off"></div>
    <p class="hint" id="fac-none" hidden>{{ __('No facilities match.') }}</p>
    @foreach($facilities as $f)
    @php($st = $f->verificationState())
    <label class="opt fac-opt"><input type="radio" name="receiving_facility_id" value="{{ $f->id }}" data-name="{{ $f->name }}" data-phone="{{ $f->phone }}" data-state="{{ $st }}" @checked(old('receiving_facility_id')==$f->id)>
        <span><b>{{ $f->name }}</b> <x-vpill :state="$st" :at="$f->info_verified_at"/><br><small>{{ __(ucfirst(str_replace('_',' ',$f->type))) }} · {{ $f->county }} · {{ $f->hours }}</small><br><small>{{ $f->services->where('available', true)->pluck('name')->take(6)->join(', ') }}</small></span></label>
    @endforeach
    <div class="sticky-act"><button class="btn quiet" type="button" data-prev>{{ __('Back') }}</button><button class="btn" type="button" data-next id="to-review">{{ __('Review') }}</button></div>
</section>

<section class="step">
    <h2 tabindex="-1">{{ __('4. Review and consent') }}</h2>
    <div class="card"><h3>{{ __('What will be shared') }}</h3>
    <dl class="kv"><dt>{{ __('Priority') }}</dt><dd id="rv-prio"></dd><dt>{{ __('To') }}</dt><dd id="rv-dest"></dd><dt>{{ __('Clinical note') }}</dt><dd id="rv-note"></dd><dt>{{ __('Documents') }}</dt><dd id="rv-docs"></dd></dl>
    <p class="hint">{{ __('Before they accept, the receiving facility sees only: priority, reason, sex and age. Name, identifier, note and documents are shared after they accept.') }}</p></div>
    <fieldset style="border:0;padding:0"><legend style="font-weight:650;margin-bottom:6px">{{ __('Consent') }}</legend>
    @foreach(config('referralflow.consent_bases') as $k => $l)
    <label class="opt"><input type="radio" name="consent_basis" value="{{ $k }}" @checked(old('consent_basis')===$k)><span>{{ __($l) }}</span></label>
    @endforeach
    @error('consent_basis')<div class="err" role="alert">{{ $message }}</div>@enderror</fieldset>
    <x-field name="consent_witness" :label="__('Witness name (optional)')" />
    <label class="opt"><input type="checkbox" name="consent_confirm" value="1" @checked(old('consent_confirm'))><span>{{ __('I have explained what is shared and with whom, and the consent above is recorded.') }}</span></label>
    @error('consent_confirm')<div class="err" role="alert">{{ $message }}</div>@enderror
    @error('consent')<div class="err" role="alert">{{ $message }}</div>@enderror
    @if($errors->has('override_reason') || old('override_reason'))
    <div class="field"><label for="override_reason">{{ __('Another referral for this patient to this facility is already open. Why create another?') }}</label><input id="override_reason" name="override_reason" value="{{ old('override_reason') }}">@error('override_reason')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    @endif
    <div class="sticky-act"><button class="btn quiet" type="button" data-prev>{{ __('Back') }}</button><button class="btn quiet js-only" type="button" id="save-draft">{{ __('Save draft on device') }}</button><button class="btn quiet" name="action" value="draft">{{ __('Save draft') }}</button><button class="btn" name="action" value="send">{{ __('Send referral') }}</button></div>
    <p class="hint" id="draft-note"></p>
</section>
</form>

<dialog id="stale-dialog" aria-labelledby="stale-h"><h2 id="stale-h">{{ __('Confirm by phone first') }}</h2><p><strong id="stale-name"></strong> {{ __('has not verified its information recently, or never has. Phone to confirm the service is available before you send.') }}</p><div class="btn-row"><button class="btn" id="stale-yes" type="button">{{ __('I have confirmed by phone') }}</button><button class="btn quiet" id="stale-no" type="button">{{ __('Choose another facility') }}</button></div></dialog>
@endsection
