@extends('layouts.app')
@section('title', __('Notifications'))
@section('content')
<div class="top"><h1>{{ __('Notifications') }}</h1><form method="post" action="{{ route('notifications.read') }}">@csrf<button class="btn quiet sm">{{ __('Mark all read') }}</button></form></div>
<div class="banner info"><div>{{ __('Notifications never include names, IDs or clinical details. Sign in to see the referral.') }}</div></div>
<div class="card">
@forelse($items as $n)
<a class="row" @if($n->referral_id) href="{{ route('referrals.show', $n->referral_id) }}" @endif><div class="grow"><div class="t">@if(!$n->read_at)<span class="chip">{{ __('New') }}</span> @endif{{ __(\App\Domain\Notifier::TEXT[$n->kind] ?? $n->text) }}</div><small>{{ $n->created_at->diffForHumans() }}</small></div></a>
@empty<p class="mute">{{ __('Nothing here.') }}</p>@endforelse
</div>
@endsection
