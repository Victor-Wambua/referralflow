@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@php
$o = $m['overall'];
$pct = fn ($v) => $v === null ? '—' : round($v * 100).'%';
$mins = fn ($v) => $v === null ? '—' : ($v >= 120 ? round($v / 60, 1).' h' : round($v).' min');
@endphp
<div class="top"><div><h1>{{ __('Dashboard') }}</h1><div class="mute">{{ __('Showing') }} {{ __($scopeLabel) }} · {{ $m['from'] }} → {{ $m['to'] }}</div></div>
<form method="get" class="filter" style="margin:0"><div class="field"><label for="days" class="sr">{{ __('Period') }}</label><select id="days" name="days" onchange="this.form.submit()">@foreach([7,30,90,365] as $d)<option value="{{ $d }}" @selected($days===$d)>{{ $d }} {{ __('days') }}</option>@endforeach</select><noscript><button class="btn sm">{{ __('Apply') }}</button></noscript></div></form></div>
<div class="banner info"><div>{{ __('Computed from referral records in this system. Aggregates only: no patient identifiers. Small groups are hidden.') }}</div></div>
<div class="grid g4 keep2">
<div class="stat"><b>{{ $o['created'] }}</b><span>{{ __('Created') }}</span></div>
<div class="stat"><b>{{ $o['sent'] }}</b><span>{{ __('Sent') }}</span></div>
<div class="stat"><b>{{ $o['accepted'] }}</b><span>{{ __('Accepted') }}</span></div>
<div class="stat"><b>{{ $o['pending'] }}</b><span>{{ __('Pending response') }}</span></div>
<div class="stat"><b>{{ $o['completed'] }}</b><span>{{ __('Completed') }}</span></div>
<div class="stat"><b>{{ $mins($o['median_ack_minutes']) }}</b><span>{{ __('Median time to acknowledge') }}</span></div>
<div class="stat"><b>{{ $mins($o['median_decision_minutes']) }}</b><span>{{ __('Median time to decision') }}</span></div>
<div class="stat"><b>{{ $pct($o['incomplete_rate']) }}</b><span>{{ __('Incomplete rate') }} ({{ $o['declined'] + $o['cancelled'] }}/{{ $o['terminal'] }})</span></div>
</div>
<div class="grid g2" style="margin-top:16px">
<div class="card"><h2>{{ __('Referrals sent per week') }}</h2>
@php($max = max(1, ...array_values($m['weekly'] ?: [0])))
@if($m['weekly'])
<div class="chart" role="img" aria-label="{{ collect($m['weekly'])->map(fn($n,$w)=>$w.': '.$n)->join('; ') }}">@foreach($m['weekly'] as $w => $n)<div style="height:{{ round($n / $max * 100) }}%"><span>{{ $n }}</span></div>@endforeach</div>
<div class="chart-x">@foreach($m['weekly'] as $w => $n)<span>{{ substr($w, 5) }}</span>@endforeach</div>
@else<p class="mute">{{ __('No referrals in this period.') }}</p>@endif</div>
<div class="card"><h2>{{ __('Why referrals did not complete') }}</h2>
@php($rmax = max(1, ...array_values($m['reasons'] ?: [0])))
<ul class="barlist">@forelse($m['reasons'] as $k => $n)@php([$kind, $code] = explode(':', $k))<li><span>{{ __(ucfirst($kind)) }}: {{ __(config('referralflow.decline_reasons.'.$code, config('referralflow.cancel_reasons.'.$code, $code))) }}</span><b>{{ $n }}</b><div class="bar"><i style="width:{{ round($n / $rmax * 100) }}%"></i></div></li>@empty<li class="mute">{{ __('None in this period.') }}</li>@endforelse</ul></div>
</div>
<div class="card"><h2>{{ __('Receiving facilities') }}</h2>
<div class="banner warn"><div><b>{{ __('Read with care.') }}</b> {{ __('Differences reflect case mix, capacity and distance as much as performance. Groups under') }} {{ $m['min_cell'] }} {{ __('are hidden. Listed alphabetically, not ranked.') }}</div></div>
<div class="tw"><table><thead><tr><th>{{ __('Facility') }}</th><th>{{ __('Referrals received') }}</th><th>{{ __('Accepted of decided') }}</th><th>{{ __('Median time to decision') }}</th><th>{{ __('Completed of closed') }}</th><th>{{ __('Pending') }}</th></tr></thead><tbody>
@forelse($m['facilities'] as $f)
<tr><td>{{ $f['facility'] }}</td>@if($f['suppressed'])<td>&lt; {{ $m['min_cell'] }}</td><td colspan="4" class="mute">{{ __('Hidden: too few referrals') }}</td>@else<td>{{ $f['received_n'] }}</td><td>{{ $pct($f['acceptance_rate']) }}</td><td>{{ $mins($f['median_decision_minutes']) }}</td><td>{{ $pct($f['completion_rate']) }}</td><td>{{ $f['pending'] }}</td>@endif</tr>
@empty<tr><td colspan="6" class="mute">{{ __('No data.') }}</td></tr>@endforelse
</tbody></table></div></div>
<form method="post" action="{{ route('dashboard.export', ['days' => $days]) }}">@csrf<button class="btn sec">{{ __('Export aggregate CSV') }}</button> <span class="hint">{{ __('Exports are logged and need re-authentication.') }}</span></form>
@endsection
