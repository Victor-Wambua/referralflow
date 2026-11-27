@extends('layouts.app')
@section('title', __('Home'))
@section('content')
<div class="top"><div><h1>{{ __('Hello') }}, {{ explode(' ', auth()->user()->name)[0] }}</h1><div class="mute">{{ auth()->user()->facility->name }}</div></div></div>
<a class="btn" style="min-height:64px;width:100%;font-size:1.15rem" href="{{ route('referrals.new') }}"><svg aria-hidden="true"><use href="#i-plus"/></svg>{{ __('New referral') }}</a>
@if($drafts)<div class="banner info" style="margin-top:14px"><div>{{ trans_choice('{1} :n draft is waiting to be sent|[2,*] :n drafts are waiting to be sent', $drafts, ['n' => $drafts]) }}</div><a class="btn sm quiet" href="{{ route('referrals', ['status' => 'created']) }}">{{ __('Open') }}</a></div>@endif
<h2 style="margin-top:22px">{{ __('Needs attention') }}</h2>
<div class="card">
@forelse($attention as $r)
<a class="row" href="{{ route('referrals.show', $r->id) }}">
    <div class="grow"><div class="t">{{ $r->displayRef() }} → {{ $r->receivingFacility->name }}</div>
    <small>@if($r->status==='declined'){{ __('Declined') }}: {{ __(config('referralflow.decline_reasons.'.$r->terminal_reason, '')) }}@elseif($r->info_requested_at && !$r->info_replied_at){{ __('More information was requested') }}@else{{ __('No response yet. Consider phoning the facility.') }}@endif</small></div>
    <x-prio :p="$r->priority"/><x-status :s="$r->status"/>
</a>
@empty
<p class="mute">{{ __('Nothing waiting.') }}</p>
@endforelse
</div>
<h2>{{ __('Recent') }}</h2>
<div class="card">
@forelse($recent as $r)
<a class="row" href="{{ route('referrals.show', $r->id) }}"><div class="grow"><div class="t">{{ $r->displayRef() }}</div><small>{{ $r->receivingFacility->name }} · {{ $r->created_at->diffForHumans() }}</small></div><x-prio :p="$r->priority"/><x-status :s="$r->status"/></a>
@empty
<p class="mute">{{ __('No referrals yet.') }}</p>
@endforelse
</div>
@endsection
