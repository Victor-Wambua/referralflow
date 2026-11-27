@extends('layouts.guest')
@section('title', __('Set up two-step sign-in'))
@section('content')
<h1>{{ __('Set up two-step sign-in') }}</h1>
<div class="card">
    <p>{{ __('Add this account to an authenticator app (any TOTP app). Enter the setup key by hand:') }}</p>
    <code class="code-box" id="setup-key">{{ $secret }}</code>
    <p class="hint">{{ __('Type: time-based, 6 digits, 30 seconds.') }}</p>
    <p class="hint"><code class="code-box">{{ $uri }}</code></p>
</div>
<form method="post" action="{{ route('mfa') }}" class="card" novalidate>
    @csrf
    <div class="field">
        <label for="code">{{ __('Code from your authenticator app') }}</label>
        <input id="code" name="code" class="otp-in" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required>
        @error('code')<div class="err" role="alert">{{ $message }}</div>@enderror
    </div>
    <button class="btn" type="submit">{{ __('Turn on and continue') }}</button>
</form>
@endsection
