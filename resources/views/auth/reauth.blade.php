@extends('layouts.app')
@section('title', __('Confirm it is you'))
@section('content')
<h1>{{ __('Confirm it is you') }}</h1>
<p>{{ __('This action is sensitive. Enter your password and a fresh code.') }}</p>
<form method="post" action="{{ route('reauth') }}" class="card" style="max-width:480px" novalidate>
    @csrf
    <div class="field"><label for="password">{{ __('Password') }}</label><input id="password" name="password" type="password" autocomplete="current-password" required>@error('password')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="code">{{ __('6-digit code') }}</label><input id="code" name="code" class="otp-in" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required>@error('code')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <button class="btn">{{ __('Confirm') }}</button>
</form>
@endsection
