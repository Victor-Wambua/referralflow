@php
$u = auth()->user();
$unread = \App\Models\UserNotice::query()->where('user_id', $u->id)->whereNull('read_at')->count();
$items = match ($u->role->value) {
    'clinician' => [['home','i-home','Home'],['referrals','i-list','My referrals'],['directory','i-dir','Directory'],['access','i-shield','Access log'],['settings','i-gear','Settings']],
    'coordinator' => [['inbox','i-inbox','Inbox'],['referrals','i-list','Referrals'],['directory','i-dir','Directory'],['dashboard','i-chart','Dashboard'],['access','i-shield','Access log'],['settings','i-gear','Settings']],
    'facility_admin' => [['admin.users','i-users','Users and roles'],['admin.breakglass','i-key','Emergency access'],['data-requests.review','i-doc','Data requests'],['directory','i-dir','Directory'],['dashboard','i-chart','Dashboard'],['access','i-shield','Access log'],['settings','i-gear','Settings']],
    'network_analyst' => [['dashboard','i-chart','Dashboard'],['directory','i-dir','Directory'],['settings','i-gear','Settings']],
    default => [['admin.facilities','i-dir','Facilities'],['admin.users','i-users','Users and roles'],['admin.audit','i-shield','Audit explorer'],['admin.retention','i-gear','Retention and security'],['admin.forms','i-doc','Referral form'],['admin.breakglass','i-key','Emergency access'],['directory','i-dir','Directory'],['dashboard','i-chart','Dashboard'],['settings','i-gear','Settings']],
};
$mobile = match ($u->role->value) {
    'clinician' => ['home','referrals','referrals.new','directory','settings'],
    'coordinator' => ['inbox','referrals','directory','dashboard','settings'],
    'facility_admin' => ['admin.users','admin.breakglass','directory','dashboard','settings'],
    'network_analyst' => ['dashboard','directory','settings'],
    default => ['admin.facilities','admin.users','admin.audit','admin.retention','settings'],
};
$inboxCount = $u->role->value === 'coordinator' ? \App\Models\Referral::query()->where('receiving_facility_id', $u->facility_id)->whereIn('status', ['sent','received'])->count() : 0;
@endphp
@if($kind === 'rail')
<nav class="nav" aria-label="{{ __('Main') }}">
@foreach($items as [$r,$i,$l])
<a href="{{ route($r) }}" @if(request()->routeIs($r) || ($r==='referrals' && request()->routeIs('referrals.*') && !request()->routeIs('referrals.new'))) aria-current="page" @endif><svg aria-hidden="true"><use href="#{{ $i }}"/></svg>{{ __($l) }}@if($r==='inbox' && $inboxCount)<span class="badge-n">{{ $inboxCount }}</span>@endif</a>
@endforeach
</nav>
@else
<nav class="tabs" aria-label="{{ __('Main') }}">
@foreach($mobile as $r)
@php($m = collect($items)->firstWhere(0, $r))
@if($r==='referrals.new')
<a href="{{ route('referrals.new') }}" class="fab"><svg aria-hidden="true"><use href="#i-plus"/></svg>{{ __('New') }}</a>
@elseif($m)
<a href="{{ route($r) }}" @if(request()->routeIs($r)) aria-current="page" @endif><svg aria-hidden="true"><use href="#{{ $m[1] }}"/></svg>{{ __(explode(' ', $m[2])[0] === 'My' ? 'Referrals' : $m[2]) }}</a>
@endif
@endforeach
</nav>
@endif
