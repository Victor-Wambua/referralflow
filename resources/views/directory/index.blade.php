@extends('layouts.app')
@section('title', __('Directory'))
@section('content')
<h1>{{ __('Facility directory') }}</h1>
<form method="get" class="filter" role="search">
    <div class="field"><label for="q">{{ __('Search name, county or service') }}</label><input id="q" name="q" type="search" value="{{ request('q') }}"></div>
    <div class="field"><label for="type">{{ __('Type') }}</label><select id="type" name="type"><option value="">{{ __('All') }}</option>@foreach($types as $t)<option value="{{ $t }}" @selected(request('type')===$t)>{{ __(ucfirst(str_replace('_',' ',$t))) }}</option>@endforeach</select></div>
    <div class="field"><label for="verification">{{ __('Verification') }}</label><select id="verification" name="verification"><option value="">{{ __('All') }}</option>@foreach(['verified'=>'Verified','due'=>'Verification due','stale'=>'Stale','unverified'=>'Unverified'] as $k=>$l)<option value="{{ $k }}" @selected(request('verification')===$k)>{{ __($l) }}</option>@endforeach</select></div>
    <div class="field" style="align-self:end"><button class="btn sec">{{ __('Search') }}</button></div>
</form>
<div class="card">
@forelse($items as $f)
<a class="row" href="{{ route('directory.show', $f) }}"><div class="grow"><div class="t">{{ $f->name }}</div><small>{{ __(ucfirst(str_replace('_',' ',$f->type))) }} · {{ $f->county }} · {{ $f->hours }}</small></div><x-vpill :state="$f->verificationState()" :at="$f->info_verified_at"/></a>
@empty
<p class="mute">{{ __('No facilities match.') }} <a href="{{ route('directory') }}">{{ __('Clear filters') }}</a></p>
@endforelse
</div>
<p class="hint">{{ __('Availability is reported by each facility with a verification time. Facility data here is synthetic.') }}</p>
@endsection
