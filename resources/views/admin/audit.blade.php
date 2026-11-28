@extends('layouts.app')
@section('title', __('Audit explorer'))
@section('content')
<h1>{{ __('Audit explorer') }}</h1>
@if($verify)<div class="banner {{ $verify['ok'] ? 'ok' : 'bad' }}" role="status"><div>{{ $verify['message'] }}</div></div>@endif
<form method="get" class="filter"><div class="field"><label for="action">{{ __('Action starts with') }}</label><input id="action" name="action" value="{{ request('action') }}"></div>
<div class="field"><label for="flag">{{ __('Flag') }}</label><select id="flag" name="flag"><option value="">{{ __('All') }}</option><option value="break_glass" @selected(request('flag')==='break_glass')>{{ __('Emergency access') }}</option><option value="export" @selected(request('flag')==='export')>Export</option><option value="data_request" @selected(request('flag')==='data_request')>Data request</option></select></div>
<div class="field" style="align-self:end"><button class="btn sec">{{ __('Filter') }}</button> <button class="btn quiet" name="verify" value="1">{{ __('Verify chain') }}</button></div></form>
<div class="card tw" role="region" tabindex="0" aria-label="{{ __('Audit explorer') }}"><table class="stack"><thead><tr><th>#</th><th>{{ __('When (UTC)') }}</th><th>{{ __('Action') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Record') }}</th><th>{{ __('Hash') }}</th></tr></thead><tbody>
@foreach($items as $e)<tr @class(['alert' => $e->flag === 'break_glass'])><td data-label="#">{{ $e->seq }}</td><td data-label="{{ __('When (UTC)') }}">{{ substr($e->occurred_at, 0, 19) }}</td><td data-label="{{ __('Action') }}">{{ $e->action }}@if($e->flag) <span class="pill p-bad">{{ $e->flag }}</span>@endif</td><td data-label="{{ __('Actor') }}">{{ $e->actor_id ?? 'system' }} {{ $e->actor_role }}</td><td data-label="{{ __('Record') }}">{{ $e->subject_type }} {{ $e->subject_id ? '…'.substr($e->subject_id, -6) : '' }}</td><td data-label="{{ __('Hash') }}"><code>{{ substr($e->hash, 0, 10) }}</code></td></tr>@endforeach
</tbody></table></div>
<nav class="pager">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">{{ __('Previous') }}</a>@endif<span aria-current="page">{{ $items->currentPage() }}</span>@if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">{{ __('Next') }}</a>@endif</nav>
<form method="post" action="{{ route('admin.audit.export') }}">@csrf<button class="btn sec">{{ __('Export CSV') }}</button> <span class="hint">{{ __('Logged. Entries are immutable and hash-chained.') }}</span></form>
@endsection
