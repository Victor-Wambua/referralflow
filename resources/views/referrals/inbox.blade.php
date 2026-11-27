@extends('layouts.app')
@section('title', __('Inbox'))
@section('content')
<h1>{{ __('Inbox') }}</h1>
<nav class="tabsbar" aria-label="{{ __('Inbox sections') }}">
@foreach($buckets as $k => $b)<a href="{{ route('inbox', ['tab' => $k]) }}" @if($tab===$k) aria-current="page" @endif>{{ __($b['label']) }} ({{ $counts[$k] }})</a>@endforeach
</nav>
<div class="card">
@forelse($items as $r)
<a class="row" href="{{ route('referrals.show', $r->id) }}">
    <div class="grow"><div class="t">{{ $r->displayRef() }} · {{ __(config('referralflow.reason_codes.'.$r->reason_code)) }}</div>
    <small>{{ $r->referringFacility->name }} · {{ __('sent') }} {{ $r->sent_at->diffForHumans() }}@if($r->escalation_level >= 1) · <b>{{ __('waiting longer than expected') }}</b>@endif</small></div>
    <x-prio :p="$r->priority"/><x-status :s="$r->status"/>
</a>
@empty
<p class="mute">{{ __('All clear.') }}</p>
@endforelse
</div>
@endsection
