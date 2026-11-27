@extends('layouts.app')
@section('title', __('Referral'))
@section('content')
@php
$steps = ['created','sent','received','accepted','scheduled','attended','completed'];
$idx = array_search($v['status'], $steps);
$terminalBad = in_array($v['status'], ['declined','cancelled']);
@endphp
<div class="top">
    <div><h1>{{ $v['ref'] }}</h1><div class="mute">{{ $v['from'] }} → {{ $v['to'] }}</div></div>
    <div class="chip-row"><x-prio :p="$v['priority']"/><x-status :s="$v['status']"/><span class="tag-syn">{{ __('Synthetic') }}</span></div>
</div>

@if($v['level']==='breakglass')<div class="banner bad" role="alert"><div><b>{{ __('Emergency access: read only') }}</b> {{ __('This view is flagged and reviewed. Access ends') }} {{ $grant?->expires_at?->format('H:i') }}.</div></div>@endif
@if($v['tombstone'])<div class="banner info"><div>{{ __('Sharing for this referral has stopped.') }}</div></div>@endif
@if($v['minimal'])<div class="banner info"><div>{{ __('You see the minimum until you accept: priority, reason, sex and age. Identity, note and documents are shared after you accept.') }}</div></div>@endif
@if($v['overdue'] && in_array($v['status'], ['sent','received']))<div class="banner warn"><div><b>{{ __('No response yet.') }}</b> {{ __('Consider phoning the facility, or referring elsewhere.') }}</div></div>@endif
@if($v['info_pending'])<div class="banner warn"><div>{{ __('More information was requested on this referral.') }} <a href="{{ route('referrals.show', [$v['id'], 'tab' => 'messages']) }}">{{ __('Open messages') }}</a></div></div>@endif
@if($v['consent'])<div class="banner {{ $v['consent']['withdrawn'] ? 'bad' : 'ok' }}"><div>@if($v['consent']['withdrawn']){{ __('Consent withdrawn') }} {{ $v['consent']['withdrawn']->diffForHumans() }}.@else{{ __('Consent recorded') }}: {{ __($v['consent']['basis']) }} · {{ $v['consent']['at']->toDateString() }}@endif</div></div>@endif

@if($terminalBad)
<div class="banner bad"><div><b>{{ __(ucfirst($v['status'])) }}</b> — {{ __(config('referralflow.decline_reasons.'.$v['terminal_reason'], config('referralflow.cancel_reasons.'.$v['terminal_reason'], ''))) }}</div></div>
@else
<ol class="track" aria-label="{{ __('Progress') }}">
@foreach($steps as $i => $s)<li class="@if($idx !== false && $i < $idx) done @elseif($i === $idx) cur @endif" @if($i === $idx) aria-current="step" @endif><i></i>{{ __($s === 'attended' ? 'Patient attended' : ucfirst($s)) }}</li>@endforeach
</ol>
@endif
@if($v['appointment_at'] && ! $terminalBad)<p><b>{{ __('Appointment') }}:</b> {{ $v['appointment_at']->format('Y-m-d H:i') }}</p>@endif

@if(count($actions) || $can_withdraw)
<div class="btn-row">
    @if(in_array('sent', $actions))<button class="btn" data-dialog="d-send" type="button">{{ __('Send referral') }}</button>@endif
    @if(in_array('accepted', $actions))<form method="post" action="{{ route('referrals.accept', $v['id']) }}" class="inline">@csrf<button class="btn">{{ __('Accept') }}</button></form>@endif
    @if(in_array('declined', $actions))<button class="btn quiet" data-dialog="d-decline" type="button">{{ __('Decline') }}</button>@endif
    @if(in_array('scheduled', $actions))<button class="btn" data-dialog="d-schedule" type="button">{{ __('Schedule appointment') }}</button>@endif
    @if($v['status']==='scheduled' && in_array('attended', $actions))<button class="btn quiet" data-dialog="d-schedule" type="button">{{ __('Change appointment') }}</button>@endif
    @if(in_array('attended', $actions))<form method="post" action="{{ route('referrals.attend', $v['id']) }}" class="inline">@csrf<button class="btn">{{ __('Patient attended') }}</button></form>@endif
    @if(in_array('completed', $actions))<button class="btn" data-dialog="d-complete" type="button">{{ __('Send summary and complete') }}</button>@endif
    @if(in_array('cancelled', $actions))<button class="btn quiet" data-dialog="d-cancel" type="button">{{ $v['side']==='receiver' ? __('Cancel or did not attend') : __('Cancel referral') }}</button>@endif
    @if($can_withdraw)<button class="btn quiet" data-dialog="d-withdraw" type="button">{{ __('Withdraw consent') }}</button>@endif
</div>
@endif

<nav class="tabsbar" aria-label="{{ __('Referral sections') }}" style="margin-top:16px">
    <a href="{{ route('referrals.show', $v['id']) }}" @if($tab==='overview') aria-current="page" @endif>{{ __('Overview') }}</a>
    <a href="{{ route('referrals.show', [$v['id'], 'tab' => 'messages']) }}" @if($tab==='messages') aria-current="page" @endif>{{ __('Messages') }}</a>
    <a href="{{ route('referrals.show', [$v['id'], 'tab' => 'history']) }}" @if($tab==='history') aria-current="page" @endif>{{ __('History') }}</a>
</nav>

@if($tab==='overview')
@if($v['tombstone'])
@else
<div class="card"><h2>{{ __('Patient') }}</h2>
@if($v['patient'])
    @if($v['patient']['revealed'])
    <dl class="kv"><dt>{{ __('Name') }}</dt><dd>{{ $v['patient']['name'] }}</dd><dt>{{ __('Date of birth') }}</dt><dd>{{ $v['patient']['dob'] }}</dd>@if($v['patient']['identifier'])<dt>{{ __('Identifier') }}</dt><dd>{{ $v['patient']['identifier'] }}</dd>@endif<dt>{{ __('Sex and age') }}</dt><dd>{{ __($v['sex']) }}, {{ $v['age'] }}</dd></dl>
    <div class="banner warn" style="margin-top:10px"><div>{{ __('Identity is visible for a few minutes and the view was logged.') }}</div><form method="post" action="{{ route('referrals.hide', $v['id']) }}">@csrf<button class="btn sm quiet">{{ __('Hide') }}</button></form></div>
    @else
    <dl class="kv"><dt>{{ __('Name') }}</dt><dd><span class="masked">{{ $v['patient']['masked_name'] }}</span></dd><dt>{{ __('Identifier') }}</dt><dd><span class="masked">{{ $v['patient']['masked_id'] }}</span></dd><dt>{{ __('Sex and age') }}</dt><dd>{{ __($v['sex']) }}, {{ $v['age'] }}</dd></dl>
    @if($v['can_reveal'])<div class="btn-row"><button class="btn sm sec" data-dialog="d-reveal" type="button">{{ __('Reveal identity') }}</button></div>@endif
    @endif
@elseif($v['purged'])<p class="mute">{{ __('Patient record removed under the retention policy.') }}</p>
@else
    <p class="mute">{{ __('Identity is shared after you accept.') }}</p><dl class="kv"><dt>{{ __('Sex and age') }}</dt><dd>{{ __($v['sex']) }}, {{ $v['age'] }}</dd></dl>
@endif
</div>
<div class="card"><h2>{{ __('Reason') }}</h2><p><b>{{ __($v['reason_label']) }}</b></p>@if($v['reason_text'])<p>{{ $v['reason_text'] }}</p>@endif</div>
@if($v['full'])
@if(count($v['form']))<div class="card"><h2>{{ __('Details') }}</h2><dl class="kv">@foreach($v['form'] as $f)<dt>{{ __($f['label']) }}</dt><dd>{{ __($f['value']) }}</dd>@endforeach</dl></div>@endif
@if($v['note'])<div class="card"><h2>{{ __('Clinical note') }}</h2><p>{{ $v['note'] }}</p></div>@endif
<div class="card"><h2>{{ __('Documents') }}</h2>
    @forelse($v['documents'] as $d)
    <div class="row"><svg width="22" height="22" aria-hidden="true"><use href="#i-doc"/></svg><div class="grow"><div class="t">{{ __('Document') }} {{ $d['n'] }}</div><small>{{ $d['mime'] === 'application/pdf' ? 'PDF' : 'Image' }} · {{ number_format($d['size']/1024, 0) }} KB · {{ $d['by'] }}</small></div><span class="pill {{ $d['scan']==='clean' ? 'p-ok' : 'p-warn' }}">{{ $d['scan']==='clean' ? __('Scanned') : __('Not scanned') }}</span><a class="btn sm quiet" href="{{ route('referrals.document', [$v['id'], $d['id']]) }}">{{ __('Open') }}</a></div>
    @empty
    <p class="mute">{{ __('No documents attached.') }}</p>
    @endforelse
    <p class="hint">{{ __('Opening a document is logged.') }}</p>
    @if($can_attach)
    <form method="post" action="{{ route('referrals.upload', $v['id']) }}" enctype="multipart/form-data">@csrf
        <div class="field"><label for="document">{{ __('Add a document') }}</label><input id="document" name="document" type="file" accept="application/pdf,image/jpeg,image/png">@error('document')<div class="err" role="alert">{{ $message }}</div>@enderror<div class="hint">{{ __('PDF, JPEG or PNG, up to 5 MB, up to 5 per referral. Do not put names in file names.') }}</div></div>
        <button class="btn sm sec">{{ __('Upload') }}</button>
    </form>
    @endif
</div>
@if($v['counter'])<div class="card"><h2>{{ __('Summary from the receiving facility') }}</h2><p><b>{{ __($v['counter']['outcome']) }}</b></p><p>{{ $v['counter']['summary'] }}</p>@if($v['counter']['follow_up'])<p><b>{{ __('Follow-up') }}:</b> {{ $v['counter']['follow_up'] }}</p>@endif</div>@endif
<p><a href="{{ route('api.fhir', $v['id']) }}">{{ __('Export as FHIR-shaped JSON') }}</a> <span class="hint">{{ __('Unvalidated against any national system.') }}</span></p>
@endif
@endif

@elseif($tab==='messages')
<div class="card">
@forelse($messages as $m)
<div class="msg {{ $m->author_facility_id === auth()->user()->facility_id ? 'me' : '' }}"><small>{{ $m->facility->name }} · {{ $m->created_at->format('Y-m-d H:i') }}@if($m->kind==='info_request') · <b>{{ __('Information requested') }}</b>@endif</small>{{ $m->body }}</div>
@empty
<p class="mute">{{ __('No messages yet.') }}</p>
@endforelse
</div>
@if($can_message)
<form method="post" action="{{ route('referrals.message', $v['id']) }}" class="card" novalidate>@csrf
    <div class="field"><label for="body">{{ __('Message') }}</label><textarea id="body" name="body" maxlength="2000">{{ old('body') }}</textarea><div class="hint">{{ __('Do not write the patient’s name or ID number.') }}</div>@error('body')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="btn-row"><button class="btn" name="kind" value="message">{{ __('Send message') }}</button>@if($v['side']==='receiver' && !in_array($v['status'], ['completed','declined','cancelled']))<button class="btn sec" name="kind" value="info_request">{{ __('Request more information') }}</button>@endif</div>
</form>
@endif

@else
<div class="card"><ul class="tl">
@foreach($v['timeline'] as $e)
<li><b>{{ __($e['to'] === 'attended' ? 'Patient attended' : ucfirst($e['to'])) }}</b> · {{ $e['at']->format('Y-m-d H:i') }}@if($e['facility']) · {{ $e['facility'] }}@endif @if($e['reason'])<br><small>{{ __($e['reason']) }}</small>@endif @if($e['note'])<br><small>{{ $e['note'] }}</small>@endif</li>
@endforeach
</ul></div>
@endif

@if($v['can_reveal'] && !($v['patient']['revealed'] ?? false))
<dialog id="d-reveal" aria-labelledby="d-reveal-h"><h2 id="d-reveal-h">{{ __('Reveal identity') }}</h2><p>{{ __('This is logged. Choose why you need it.') }}</p>
<form method="post" action="{{ route('referrals.reveal', $v['id']) }}">@csrf
<div class="field"><label for="reveal-reason">{{ __('Reason') }}</label><select id="reveal-reason" name="reason" required>@foreach($reveal_reasons as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
<div class="btn-row"><button class="btn">{{ __('Reveal') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@if(in_array('sent', $actions))
<dialog id="d-send" aria-labelledby="d-send-h"><h2 id="d-send-h">{{ __('Send referral') }}</h2>
@if(in_array($facility_state, ['stale','unverified']))<div class="banner warn"><div>{{ __('The destination’s information is not recently verified. Phone to confirm before sending.') }}</div></div>@endif
<form method="post" action="{{ route('referrals.send', $v['id']) }}">@csrf
@foreach($consent_bases as $k => $l)<label class="opt"><input type="radio" name="consent_basis" value="{{ $k }}" required><span>{{ __($l) }}</span></label>@endforeach
<div class="field"><label for="consent_witness">{{ __('Witness name (optional)') }}</label><input id="consent_witness" name="consent_witness"></div>
<label class="opt"><input type="checkbox" name="consent_confirm" value="1" required><span>{{ __('I have explained what is shared and with whom, and the consent above is recorded.') }}</span></label>
@if(in_array($facility_state, ['stale','unverified']))<label class="opt"><input type="checkbox" name="stale_confirmed" value="1"><span>{{ __('I have confirmed by phone.') }}</span></label>@endif
@error('consent')<div class="err" role="alert">{{ $message }}</div>@enderror @error('stale_confirmed')<div class="err" role="alert">{{ $message }}</div>@enderror @error('override_reason')<div class="err" role="alert">{{ $message }}</div>@enderror
<div class="field"><label for="override_reason">{{ __('Only if another referral for this patient is already open: why?') }}</label><input id="override_reason" name="override_reason"></div>
<div class="btn-row"><button class="btn">{{ __('Send') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@if(in_array('declined', $actions))
<dialog id="d-decline" aria-labelledby="d-decline-h"><h2 id="d-decline-h">{{ __('Decline referral') }}</h2>
<form method="post" action="{{ route('referrals.decline', $v['id']) }}">@csrf
<div class="field"><label for="decline-reason">{{ __('Reason (required)') }}</label><select id="decline-reason" name="reason" required><option value="">{{ __('Choose') }}</option>@foreach($declines as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>@error('reason')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<div class="field"><label for="decline-note">{{ __('Note, for example an alternative facility (no patient names)') }}</label><textarea id="decline-note" name="note" maxlength="500" style="min-height:80px"></textarea></div>
<div class="btn-row"><button class="btn danger">{{ __('Decline') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@if(in_array('scheduled', $actions) || ($v['status']==='scheduled' && in_array('attended', $actions)))
<dialog id="d-schedule" aria-labelledby="d-sched-h"><h2 id="d-sched-h">{{ __('Appointment') }}</h2>
<form method="post" action="{{ route('referrals.schedule', $v['id']) }}">@csrf
<div class="field"><label for="appointment_at">{{ __('Date and time') }}</label><input id="appointment_at" name="appointment_at" type="datetime-local" required>@error('appointment_at')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<div class="btn-row"><button class="btn">{{ __('Save') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@if(in_array('completed', $actions))
<dialog id="d-complete" aria-labelledby="d-comp-h"><h2 id="d-comp-h">{{ __('Summary back to the referring facility') }}</h2>
<form method="post" action="{{ route('referrals.complete', $v['id']) }}">@csrf
<div class="field"><label for="outcome">{{ __('Outcome') }}</label><select id="outcome" name="outcome" required>@foreach($outcomes as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
<div class="field"><label for="summary">{{ __('Summary') }}</label><textarea id="summary" name="summary" required maxlength="4000"></textarea>@error('summary')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<div class="field"><label for="follow_up">{{ __('Follow-up needed (optional)') }}</label><textarea id="follow_up" name="follow_up" maxlength="2000" style="min-height:80px"></textarea></div>
<div class="btn-row"><button class="btn">{{ __('Send and complete') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@if(in_array('cancelled', $actions))
<dialog id="d-cancel" aria-labelledby="d-canc-h"><h2 id="d-canc-h">{{ __('Cancel referral') }}</h2>
<form method="post" action="{{ route('referrals.cancel', $v['id']) }}">@csrf
<div class="field"><label for="cancel-reason">{{ __('Reason (required)') }}</label><select id="cancel-reason" name="reason" required><option value="">{{ __('Choose') }}</option>@foreach($cancels as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select>@error('reason')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<div class="field"><label for="cancel-note">{{ __('Note (no patient names)') }}</label><textarea id="cancel-note" name="note" maxlength="500" style="min-height:80px"></textarea></div>
<div class="btn-row"><button class="btn danger">{{ __('Cancel referral') }}</button><button class="btn quiet" type="button" data-close>{{ __('Keep it') }}</button></div></form></dialog>
@endif
@if($can_withdraw)
<dialog id="d-withdraw" aria-labelledby="d-wd-h"><h2 id="d-wd-h">{{ __('Withdraw consent') }}</h2><p>{{ __('Sharing stops and the referral is cancelled if it has not been attended.') }}</p>
<form method="post" action="{{ route('referrals.withdraw', $v['id']) }}">@csrf<div class="btn-row"><button class="btn danger">{{ __('Withdraw consent') }}</button><button class="btn quiet" type="button" data-close>{{ __('Cancel') }}</button></div></form></dialog>
@endif
@endsection
