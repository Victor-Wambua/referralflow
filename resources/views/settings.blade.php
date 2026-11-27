@extends('layouts.app')
@section('title', __('Settings'))
@section('content')
@php($u = auth()->user())
<h1>{{ __('Settings') }}</h1>
<div class="card"><h2>{{ __('Appearance') }}</h2><div class="btn-row"><button class="btn quiet sm" data-theme-set="auto" type="button">{{ __('Match device') }}</button><button class="btn quiet sm" data-theme-set="light" type="button">{{ __('Light') }}</button><button class="btn quiet sm" data-theme-set="dark" type="button">{{ __('Dark') }}</button></div></div>
<form method="post" action="{{ route('settings') }}" class="card">@csrf<h2>{{ __('Language') }}</h2><div class="seg"><label class="opt"><input type="radio" name="locale" value="en" @checked($u->locale==='en')><span>English</span></label><label class="opt"><input type="radio" name="locale" value="sw" @checked($u->locale==='sw')><span>Kiswahili</span></label></div><p class="hint">{{ __('Kiswahili copy is a draft and has not been reviewed by clinicians or a certified translator.') }}</p><button class="btn sm">{{ __('Save') }}</button></form>
<div class="card"><h2>{{ __('Security') }}</h2><p>{{ __('Two-step sign-in is on and cannot be turned off.') }}</p><p>{{ __('You are signed out after') }} {{ config('referralflow.session.idle_minutes') }} {{ __('minutes of inactivity.') }}</p><a class="btn quiet sm" href="{{ route('password.edit') }}">{{ __('Change password') }}</a></div>
<div class="card"><h2>{{ __('Offline') }}</h2><p>{{ __('Patient details are never stored on this device. Offline you can prepare a draft without patient details.') }}</p><a class="btn quiet sm" href="{{ route('offline') }}">{{ __('Open offline page') }}</a></div>
@if($u->isClinical())<p><a href="{{ route('data-requests') }}">{{ __('Data requests (export or deletion)') }}</a></p>@endif
<form method="post" action="{{ route('logout') }}" data-signout>@csrf<button class="btn">{{ __('Sign out') }}</button></form>
@endsection
