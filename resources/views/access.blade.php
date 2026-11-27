@extends('layouts.app')
@section('title', __('Access log'))
@section('content')
<h1>{{ __('Access log') }}</h1>
<p class="mute">{{ __('Showing') }} {{ __($scope) }}. {{ __('Every view of patient information is recorded here. Entries cannot be edited.') }}</p>
<div class="card tw" role="region" tabindex="0" aria-label="{{ __('Access log') }}"><table><thead><tr><th>#</th><th>{{ __('When (UTC)') }}</th><th>{{ __('Action') }}</th><th>{{ __('Who') }}</th><th>{{ __('Record') }}</th><th>{{ __('Reason') }}</th></tr></thead><tbody>
@foreach($items as $e)
<tr @if($e->flag==='break_glass') style="background:var(--bad-soft)" @endif><td>{{ $e->seq }}</td><td>{{ str_replace(['T','Z'], [' ',''], substr($e->occurred_at, 0, 19)) }}</td><td>{{ $e->action }}@if($e->flag) <span class="pill p-bad">{{ $e->flag === 'break_glass' ? __('Emergency access') : $e->flag }}</span>@endif</td><td>{{ $e->actor_id ?? 'system' }} ({{ $e->actor_role }})</td><td>{{ $e->subject_type }} {{ $e->subject_id ? '…'.substr($e->subject_id, -6) : '' }}</td><td>{{ $e->reason }}</td></tr>
@endforeach
</tbody></table></div>
<nav class="pager">@if($items->previousPageUrl())<a href="{{ $items->previousPageUrl() }}">{{ __('Previous') }}</a>@endif<span aria-current="page">{{ $items->currentPage() }}</span>@if($items->nextPageUrl())<a href="{{ $items->nextPageUrl() }}">{{ __('Next') }}</a>@endif</nav>
@endsection
