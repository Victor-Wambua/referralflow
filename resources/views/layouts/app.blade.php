@extends('layouts.base')
@section('body')
@php($u = auth()->user())
<div class="shell">
<aside class="side">
    <a class="brand" href="{{ route('home') }}"><img src="/mark.svg" alt="" width="34" height="34">ReferralFlow</a>
    @include('partials.nav', ['kind' => 'rail'])
    <div class="rail-foot">
        <div><strong>{{ $u->name }}</strong></div>
        <div>{{ __($u->role->label()) }}@if($u->facility) · {{ $u->facility->name }}@endif</div>
        <p><a href="{{ route('notifications') }}">{{ __('Notifications') }}</a></p>
        <form method="post" action="{{ route('logout') }}" data-signout>@csrf<button class="btn quiet sm">{{ __('Sign out') }}</button></form>
    </div>
</aside>
<div>
<main id="main" class="main">
    <div id="offline-banner" class="banner warn" hidden role="status"><div>{{ __('You are offline.') }} <a href="{{ route('offline') }}">{{ __('Patient details cannot be saved offline. You can prepare a draft without patient details.') }}</a></div></div>
    @if(session('status'))<div class="banner ok" role="status"><div>{{ session('status') }}</div></div>@endif
    @if($errors->has('status'))<div class="banner bad" role="alert"><div>{{ $errors->first('status') }}</div></div>@endif
    @if(session('recovery_codes'))
    <div class="banner warn" role="status"><div><b>{{ __('Save your recovery codes now. They are shown once.') }}</b><div class="rec-codes">@foreach(session('recovery_codes') as $c)<div>{{ $c }}</div>@endforeach</div></div></div>
    @endif
    @if(session('temp_password'))
    <div class="banner warn" role="status"><div><b>{{ __('One-time temporary password for') }} {{ session('temp_for') }}:</b> <span class="code-box">{{ session('temp_password') }}</span><span>{{ __('Share it securely. It must be changed at first sign-in. It is not shown again.') }}</span></div></div>
    @endif
    @yield('content')
</main>
</div>
</div>
@include('partials.nav', ['kind' => 'tabs'])
@endsection
