@extends('layouts.app')
@section('title', __('Referral form'))
@section('content')
<h1>{{ __('Referral form schema') }}</h1>
<div class="banner info"><div>{{ __('A generic, versioned form. It is not any official Kenyan form (the Community Referral Form is a different document). Mapping to official forms must be done with the Ministry and facility partners.') }}</div></div>
<div class="card">@foreach($schemas as $s)<div class="row"><div class="grow"><div class="t">{{ $s->name }}</div><small>{{ $s->key }} v{{ $s->version }} · {{ count($s->fields) }} {{ __('fields') }}</small></div><span class="pill {{ $s->status==='active' ? 'p-ok' : 'p-mute' }}">{{ $s->status }}</span>@if($s->status!=='active')<form method="post" action="{{ route('admin.forms.activate', $s) }}">@csrf<button class="btn sm quiet">{{ __('Activate') }}</button></form>@endif</div>@endforeach</div>
<form method="post" action="{{ route('admin.forms.store') }}" class="card" novalidate>@csrf<h2>{{ __('New version') }}</h2>
<x-field name="key" :label="__('Schema key')" value="generic-referral" /><x-field name="name" :label="__('Name')" />
<div class="field"><label for="fields">{{ __('Fields (JSON list)') }}</label><textarea id="fields" name="fields" style="min-height:220px;font-family:var(--font-mono)">{{ old('fields', json_encode(optional($schemas->first())->fields ?? [], JSON_PRETTY_PRINT)) }}</textarea><div class="hint">{{ __('Each field: key, label, type') }} ({{ implode(', ', $types) }}), required, options, max.</div>@error('fields')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<button class="btn">{{ __('Save as draft version') }}</button></form>
@endsection
