@props(['state', 'at' => null])
@php($map = ['verified' => ['p-ok', 'Verified'], 'due' => ['p-warn', 'Verification due'], 'stale' => ['p-bad', 'Stale'], 'unverified' => ['p-mute', 'Unverified']])
<span class="pill {{ $map[$state][0] }}">{{ __($map[$state][1]) }}@if($at && $state !== 'unverified') · {{ $at->diffForHumans() }}@endif</span>
