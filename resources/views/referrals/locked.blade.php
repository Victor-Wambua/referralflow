@extends('layouts.app')
@section('title', __('No access'))
@section('content')
<div class="lock">
    <h1>{{ __('You do not have access to this referral') }}</h1>
    <p>{{ __('It may not involve your facility, or it may not exist.') }}</p>
    <div class="card" style="text-align:left">
        <h2>{{ __('Emergency access') }}</h2>
        <p>{{ __('Only if a patient’s safety depends on it. Your reason is recorded, your facility lead is told, and access ends after') }} {{ config('referralflow.break_glass.minutes') }} {{ __('minutes.') }}</p>
        <form method="post" action="{{ route('breakglass.request', $id) }}" novalidate>
            @csrf
            <div class="field"><label for="reason">{{ __('Reason (do not write the patient’s name)') }}</label><textarea id="reason" name="reason" required minlength="{{ config('referralflow.break_glass.min_reason_length') }}">{{ old('reason') }}</textarea>@error('reason')<div class="err" role="alert">{{ $message }}</div>@enderror</div>
            <label class="opt"><input type="checkbox" name="confirm_emergency" value="1" required><span>{{ __('I confirm this is an emergency and I understand this access is flagged for review.') }}</span></label>
            @error('confirm_emergency')<div class="err" role="alert">{{ $message }}</div>@enderror
            <button class="btn danger">{{ __('Request emergency access') }}</button>
        </form>
    </div>
    <a href="{{ route('home') }}">{{ __('Back to home') }}</a>
</div>
@endsection
