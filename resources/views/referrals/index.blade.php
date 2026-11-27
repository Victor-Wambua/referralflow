@extends('layouts.app')
@section('title', __('Referrals'))
@section('content')
<div class="top"><h1>{{ __('Referrals') }}</h1>@if($user->isClinical())<a class="btn" href="{{ route('referrals.new') }}">{{ __('New referral') }}</a>@endif</div>
<form method="get" class="filter" role="search">
    <div class="field"><label for="q">{{ __('Search by reference or reason') }}</label><input id="q" name="q" value="{{ request('q') }}" type="search" autocomplete="off"><div class="hint">{{ __('Names are not searchable.') }}</div></div>
    <div class="field"><label for="status">{{ __('Status') }}</label><select id="status" name="status"><option value="">{{ __('All') }}</option>@foreach(config('referralflow.statuses') as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ __(ucfirst($s==='attended'?'Patient attended':$s)) }}</option>@endforeach</select></div>
    <div class="field" style="align-self:end"><button class="btn sec">{{ __('Filter') }}</button></div>
</form>
<div class="card">
@forelse($page as $r)
<a class="row" href="{{ route('referrals.show', $r->id) }}">
    <div class="grow"><div class="t">{{ $r->displayRef() }} · {{ __(config('referralflow.reason_codes.'.$r->reason_code)) }}</div>
    <small>{{ $r->referringFacility->name }} → {{ $r->receivingFacility->name }} · {{ ($r->sent_at ?? $r->created_at)->diffForHumans() }}</small></div>
    <x-prio :p="$r->priority"/><x-status :s="$r->status"/>
</a>
@empty
<p class="mute">{{ __('No referrals match.') }} @if(request()->hasAny(['q','status']))<a href="{{ route('referrals') }}">{{ __('Clear filters') }}</a>@endif</p>
@endforelse
</div>
@if($page->hasPages())
<nav class="pager" aria-label="{{ __('Pages') }}">
    @if($page->previousPageUrl())<a href="{{ $page->previousPageUrl() }}">{{ __('Previous') }}</a>@endif
    <span aria-current="page">{{ $page->currentPage() }} / {{ $page->lastPage() }}</span>
    @if($page->nextPageUrl())<a href="{{ $page->nextPageUrl() }}">{{ __('Next') }}</a>@endif
</nav>
@endif
@endsection
