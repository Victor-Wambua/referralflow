@extends('layouts.guest')
@section('title', __('Verify'))
@section('content')
<h1>{{ __('Enter your 6-digit code') }}</h1>
<form method="post" action="{{ route('mfa') }}" class="card" novalidate>
    @csrf
    <div class="field">
        <label for="code">{{ __('Code from your authenticator app') }}</label>
        <input id="code" name="code" class="otp-in" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus>
        @error('code')<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    <button class="btn" type="submit">{{ __('Verify') }}</button>
</form>
<details class="card"><summary>{{ __('Use a recovery code instead') }}</summary>
<form method="post" action="{{ route('mfa.recovery') }}">@csrf
    <div class="field"><label for="recovery_code">{{ __('Recovery code') }}</label><input id="recovery_code" name="recovery_code" autocomplete="off">@error('recovery_code')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <button class="btn sec">{{ __('Use recovery code') }}</button>
</form></details>
<form method="post" action="{{ route('logout') }}" data-signout>@csrf<button class="btn quiet sm">{{ __('Cancel and sign out') }}</button></form>
@endsection
