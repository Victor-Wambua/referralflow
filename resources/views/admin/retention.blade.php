@extends('layouts.app')
@section('title', __('Retention and security'))
@section('content')
<h1>{{ __('Retention and security') }}</h1>
<div class="banner warn"><div><b>{{ __('Placeholders only.') }}</b> {{ __('Retention periods must be set with legal advice. The Digital Health Act may require much longer retention. Confirm with counsel. The audit log is never purged by this system.') }}</div></div>
@if(session('purge'))@php($pg = session('purge'))<div class="banner {{ $pg['dry_run'] ? 'info' : 'ok' }}" role="status"><div><b>{{ $pg['dry_run'] ? __('Dry run (nothing changed)') : __('Purge complete') }}:</b> {{ $pg['referrals'] }} {{ __('referrals') }}, {{ $pg['messages'] }} {{ __('messages') }}, {{ $pg['attachments'] }} {{ __('documents') }}, {{ $pg['drafts'] }} {{ __('drafts') }}; {{ $pg['held'] }} {{ __('skipped: legal hold') }}</div></div>@endif
<form method="post" action="{{ route('admin.retention.update') }}" class="card" novalidate>@csrf<h2>{{ __('Periods (days)') }}</h2>
@foreach(['closed_referral_days' => 'Closed referral content', 'message_days' => 'Messages on closed referrals', 'attachment_days' => 'Documents on closed referrals', 'draft_days' => 'Unsent drafts'] as $k => $l)
<div class="field"><label for="{{ $k }}">{{ __($l) }}</label><input id="{{ $k }}" name="{{ $k }}" type="number" min="1" max="36500" value="{{ old($k, $values[$k]) }}">@error($k)<div class="err" role="alert">{{ $message }}</div>@enderror</div>
@endforeach
<h2>{{ __('Directory verification (days)') }}</h2>
<div class="field"><label for="due_days">{{ __('Verification due after') }}</label><input id="due_days" name="due_days" type="number" min="1" value="{{ old('due_days', $due) }}">@error('due_days')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<div class="field"><label for="stale_days">{{ __('Stale after') }}</label><input id="stale_days" name="stale_days" type="number" min="2" value="{{ old('stale_days', $stale) }}">@error('stale_days')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<p class="hint">{{ __('Two-step sign-in is locked on. Session timeout is') }} {{ config('referralflow.session.idle_minutes') }} {{ __('minutes') }}.</p>
<button class="btn">{{ __('Save') }}</button></form>
<form method="post" action="{{ route('admin.retention.purge') }}" class="card">@csrf<h2>{{ __('Run retention now') }}</h2><p class="hint">{{ __('Removes clinical content from closed referrals past their period. Dashboards keep working: non-identifying fields stay.') }}</p><div class="btn-row"><button class="btn sec">{{ __('Dry run') }}</button><button class="btn danger" name="confirm" value="1">{{ __('Purge now') }}</button></div></form>
<div class="card"><h2>{{ __('Legal holds') }}</h2>
@forelse($holds as $h)<div class="row"><div class="grow"><div class="t">{{ $h->referral_id ? __('Referral').' …'.substr($h->referral_id, -6) : __('Patient').' '.$h->patient_id }}</div><small>{{ $h->reason }} · {{ $h->placed_at->toDateString() }}@if($h->released_at) · {{ __('released') }}@endif</small></div>@unless($h->released_at)<form method="post" action="{{ route('admin.holds.release', $h) }}">@csrf<button class="btn sm quiet">{{ __('Release') }}</button></form>@endunless</div>@empty<p class="mute">{{ __('No holds.') }}</p>@endforelse
<form method="post" action="{{ route('admin.holds') }}" novalidate>@csrf<x-field name="referral_id" :label="__('Referral id (26 characters) or')" /><x-field name="patient_id" type="number" :label="__('Patient record number')" /><x-field name="reason" :label="__('Reason')" /><button class="btn sm sec">{{ __('Place hold') }}</button></form></div>
@endsection
