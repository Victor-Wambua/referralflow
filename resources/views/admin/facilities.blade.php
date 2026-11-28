@extends('layouts.app')
@section('title', __('Facilities'))
@section('content')
<h1>{{ __('Facilities') }}</h1>
@if($reports)<div class="banner warn"><div>{{ $reports }} {{ __('directory change reports are waiting.') }}</div></div>@endif
@if(session('import'))
@php($im = session('import'))
<div class="banner {{ count($im['errors']) ? 'warn' : 'ok' }}" role="status"><div><b>{{ $im['dry_run'] ? __('Dry run') : __('Import done') }}:</b> {{ $im['created'] }} {{ __('created') }}, {{ $im['updated'] }} {{ __('updated') }}, {{ count($im['errors']) }} {{ __('errors') }}@foreach($im['errors'] as $e)<div>{{ __('Line') }} {{ $e['line'] }}: {{ $e['message'] }}</div>@endforeach</div></div>
@endif
<div class="card tw" role="region" tabindex="0" aria-label="{{ __('Facilities') }}"><table class="stack"><thead><tr><th>{{ __('Facility') }}</th><th>{{ __('Type') }}</th><th>{{ __('County') }}</th><th>{{ __('Verification') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
@foreach($items as $f)<tr><td data-label="{{ __('Facility') }}" class="fac"><a href="{{ route('directory.show', $f) }}">{{ $f->name }}</a><br><small class="mute">{{ $f->mfl_code }}</small></td><td data-label="{{ __('Type') }}">{{ __(ucfirst(str_replace('_',' ',$f->type))) }}</td><td data-label="{{ __('County') }}">{{ $f->county }}</td><td data-label="{{ __('Verification') }}"><x-vpill :state="$f->verificationState()" :at="$f->info_verified_at"/></td><td data-label="{{ __('Status') }}">{{ $f->is_active ? __('Active') : __('Inactive') }}</td></tr>@endforeach
</tbody></table></div>
<form method="post" action="{{ route('admin.facilities.store') }}" class="card" novalidate><h2>{{ __('Register a facility') }}</h2><p class="hint">{{ __('New facilities start unverified.') }}</p>@csrf
<x-field name="name" :label="__('Name')" required /><x-field name="mfl_code" :label="__('MFL code (optional)')" />
<div class="field"><label for="type">{{ __('Type') }}</label><select id="type" name="type">@foreach($types as $t)<option value="{{ $t }}">{{ __(ucfirst(str_replace('_',' ',$t))) }}</option>@endforeach</select></div>
<x-field name="county" :label="__('County')" required /><x-field name="phone" :label="__('Phone')" /><x-field name="hours" :label="__('Hours')" />
<button class="btn">{{ __('Register') }}</button></form>
<form method="post" action="{{ route('admin.facilities.import') }}" enctype="multipart/form-data" class="card"><h2>{{ __('Import from a KMHFL-style CSV') }}</h2>@csrf
<p class="hint">{{ __('Format is documented in docs/INTEROPERABILITY.md. Synthetic data only. Imported services start unverified.') }}</p>
<div class="field"><label for="csv">{{ __('CSV file') }}</label><input id="csv" type="file" name="csv" accept=".csv,text/csv">@error('csv')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
<label class="opt"><input type="checkbox" name="dry_run" value="1" checked><span>{{ __('Dry run first (nothing is saved)') }}</span></label><button class="btn">{{ __('Import') }}</button></form>
@endsection
