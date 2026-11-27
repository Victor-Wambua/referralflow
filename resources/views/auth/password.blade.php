@extends('layouts.app')
@section('title', __('Change password'))
@section('content')
<h1>{{ __('Change password') }}</h1>
@if(auth()->user()->must_change_password)<div class="banner warn"><div>{{ __('You must choose a new password before continuing.') }}</div></div>@endif
<form method="post" action="{{ route('password.update') }}" class="card" style="max-width:480px" novalidate>
    @csrf
    <div class="field"><label for="current_password">{{ __('Current password') }}</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>@error('current_password')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="password">{{ __('New password') }}</label><input id="password" name="password" type="password" autocomplete="new-password" required><div class="hint">{{ __('At least 12 characters with upper case, lower case and a number.') }}</div>@error('password')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
    <div class="field"><label for="password_confirmation">{{ __('Repeat new password') }}</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
    <button class="btn">{{ __('Save password') }}</button>
</form>
@endsection
