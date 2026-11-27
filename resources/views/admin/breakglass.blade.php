@extends('layouts.app')
@section('title', __('Emergency access'))
@section('content')
<h1>{{ __('Emergency access reviews') }}</h1>
<p class="mute">{{ __('Each emergency access is flagged. A lead reviews it and says whether it was appropriate. You cannot review your own.') }}</p>
<div class="card">
@forelse($items as $g)
<div class="row" style="align-items:flex-start"><div class="grow"><div class="t">{{ $g->user->name }} · …{{ substr($g->referral_id, -6) }}</div><small>{{ $g->granted_at->format('Y-m-d H:i') }} → {{ $g->expires_at->format('H:i') }}</small><p>{{ $g->reason }}</p>
@if($g->reviewed_at)<span class="pill {{ $g->review_outcome==='appropriate' ? 'p-ok' : 'p-bad' }}">{{ __(ucfirst($g->review_outcome)) }}</span> <small>{{ $g->review_note }}</small>
@elseif($g->user_id !== auth()->id())
<form method="post" action="{{ route('admin.breakglass.review', $g) }}">@csrf<div class="field"><label for="note-{{ $g->id }}">{{ __('Review note') }}</label><input id="note-{{ $g->id }}" name="note" required></div><div class="btn-row"><button class="btn sm" name="outcome" value="appropriate">{{ __('Appropriate') }}</button><button class="btn sm danger" name="outcome" value="inappropriate">{{ __('Not appropriate') }}</button></div></form>@endif
</div></div>
@empty<p class="mute">{{ __('No emergency access yet.') }}</p>@endforelse
</div>
@endsection
