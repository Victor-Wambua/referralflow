@extends('layouts.guest')
@section('title', __('Sign in'))
@section('content')
<h1>{{ __('Sign in') }}</h1>
@if(session('notice') === 'timeout')<div class="banner warn" role="status"><div>{{ __('You were signed out after a period of inactivity, to protect patient privacy.') }}</div></div>@endif
@if(session('notice') === 'signed_out')<div class="banner ok" role="status"><div>{{ __('You are signed out.') }}</div></div>@endif
<form method="post" action="{{ route('login') }}" class="card" novalidate>
    @csrf
    <x-field name="email" type="email" :label="__('Work email')" autocomplete="username" required />
    <div class="field">
        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    <button class="btn" type="submit">{{ __('Continue') }}</button>
    <p class="hint">{{ __('Every account uses a second step: a 6-digit code from an authenticator app.') }}</p>
</form>
@endsection
