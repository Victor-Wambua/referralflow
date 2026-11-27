@extends('layouts.app')
@section('title', __('Facility'))
@section('content')
@php($st = $f->verificationState())
<div class="top"><div><h1>{{ $f->name }}</h1><div class="mute">{{ $f->mfl_code ? 'MFL '.$f->mfl_code.' · ' : '' }}{{ __(ucfirst(str_replace('_',' ',$f->type))) }}@if($f->keph_level) · KEPH {{ $f->keph_level }}@endif · {{ $f->county }}</div></div><x-vpill :state="$st" :at="$f->info_verified_at"/></div>
@if(in_array($st, ['stale','unverified']))<div class="banner warn"><div><b>{{ $st === 'stale' ? __('This information is stale.') : __('This information has never been verified.') }}</b> {{ __('Phone to confirm before sending a referral.') }}</div></div>@endif
<div class="grid g2">
<div class="card"><h2>{{ __('Contact and hours') }}</h2><dl class="kv"><dt>{{ __('Phone') }}</dt><dd>{{ $f->phone ?: '—' }}</dd><dt>{{ __('Email') }}</dt><dd>{{ $f->email ?: '—' }}</dd><dt>{{ __('Hours') }}</dt><dd>{{ $f->hours ?: '—' }}</dd><dt>{{ __('Ownership') }}</dt><dd>{{ $f->ownership ?: '—' }}</dd><dt>{{ __('Verified') }}</dt><dd>{{ $f->info_verified_at ? $f->info_verified_at->format('Y-m-d H:i') : __('Never') }}</dd><dt>{{ __('Data origin') }}</dt><dd>{{ $f->data_origin }}</dd></dl></div>
<div class="card"><h2>{{ __('Services') }}</h2>
@forelse($f->services as $s)
<div class="row" style="min-height:48px"><div class="grow">{{ $s->name }}</div><span class="pill {{ $s->available ? 'p-ok' : 'p-bad' }}">{{ $s->available ? __('Available') : __('Not available') }}</span><x-vpill :state="$s->verificationState()" :at="$s->verified_at"/></div>
@empty<p class="mute">{{ __('No services listed.') }}</p>@endforelse
</div></div>
@if($canVerify)
<form method="post" action="{{ route('directory.verify', $f) }}" class="card">@csrf
<h2>{{ __('Verify availability') }}</h2><p class="hint">{{ __('Tick what is available today. Saving records you and the time.') }}</p>
@foreach($f->services as $s)<label class="opt"><input type="checkbox" name="available[]" value="{{ $s->id }}" @checked($s->available)><span>{{ $s->name }}</span></label>@endforeach
<button class="btn">{{ __('Mark verified now') }}</button>
</form>
<form method="post" action="{{ route('directory.service', $f) }}" class="card">@csrf<div class="field"><label for="svc">{{ __('Add a service') }}</label><input id="svc" name="name" maxlength="120"></div><button class="btn sm sec">{{ __('Add') }}</button></form>
@endif
@if(auth()->user()->isClinical() || auth()->user()->role->value === 'facility_admin')
<form method="post" action="{{ route('directory.report', $f) }}" class="card">@csrf<h2>{{ __('Report a change') }}</h2><div class="field"><label for="message">{{ __('What changed? (no patient details)') }}</label><input id="message" name="message" maxlength="500" required>@error('message')<div class="err" role="alert">{{ $message }}</div>@enderror</div><button class="btn sm sec">{{ __('Send report') }}</button></form>
@endif
@endsection
