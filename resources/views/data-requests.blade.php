@extends('layouts.app')
@section('title', __('Data requests'))
@section('content')
<h1>{{ __('Data requests') }}</h1>
<div class="banner warn"><div>{{ __('Export or deletion requests for a patient. Deletion is refused while a legal hold or an open referral exists. Legal duties to keep health records may override deletion: confirm with counsel.') }}</div></div>
<div class="card">
@forelse($items as $r)
<div class="row" style="align-items:flex-start"><div class="grow"><div class="t">{{ __(ucfirst($r->type)) }} · {{ $r->created_at->toDateString() }}</div><small>{{ __('Requested by') }} {{ $r->requester->name }} · {{ __('due') }} {{ $r->due_at?->toDateString() }}</small>@if($r->decision_note)<p>{{ $r->decision_note }}</p>@endif</div><span class="pill {{ $r->status==='completed' ? 'p-ok' : ($r->status==='open' ? 'p-info' : 'p-warn') }}">{{ $r->status }}</span>
@if($canDecide && $r->status==='open')
<form method="post" action="{{ route('data-requests.approve', $r) }}">@csrf<button class="btn sm">{{ __('Approve') }}</button></form>
<form method="post" action="{{ route('data-requests.reject', $r) }}">@csrf<input name="note" required placeholder="{{ __('Reason') }}" aria-label="{{ __('Reason') }}" style="width:160px;min-height:40px"><button class="btn sm quiet">{{ __('Reject') }}</button></form>
@endif</div>
@empty<p class="mute">{{ __('No requests.') }}</p>@endforelse
</div>
@if($patients->count())
<form method="post" action="{{ route('data-requests.store') }}" class="card">@csrf<h2>{{ __('Open a request') }}</h2>
<div class="field"><label for="patient_id">{{ __('Patient') }}</label><select id="patient_id" name="patient_id">@foreach($patients as $p)<option value="{{ $p['id'] }}">{{ $p['label'] }}</option>@endforeach</select></div>
<div class="field"><label for="type">{{ __('Type') }}</label><select id="type" name="type"><option value="export">{{ __('Export') }}</option><option value="deletion">{{ __('Deletion') }}</option></select></div>
<button class="btn">{{ __('Open request') }}</button></form>
@endif
@endsection
