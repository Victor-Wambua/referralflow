@extends('layouts.base')
@section('title', __('Offline'))
@section('body')
<main id="main" class="login" style="max-width:560px">
<p class="brand"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p>
<h1>{{ __('You are offline') }}</h1>
<div class="banner warn"><div>{{ __('Patient details cannot be saved offline. You can prepare the destination and priority now.') }}</div></div>
<div id="offline-app" class="card" data-saved="{{ __('Draft saved on this device without patient details.') }}">
    <h2>{{ __('Draft without patient details') }}</h2>
    <fieldset style="border:0;padding:0"><legend style="font-weight:650">{{ __('Priority') }}</legend><div class="seg">@foreach(['emergency'=>'Emergency','urgent'=>'Urgent','routine'=>'Routine'] as $k=>$l)<label class="opt"><input type="radio" name="od-prio" value="{{ $k }}"><span>{{ __($l) }}</span></label>@endforeach</div></fieldset>
    <div class="field"><label for="od-reason">{{ __('Reason category') }}</label><select id="od-reason"><option value="">{{ __('Choose') }}</option>@foreach(config('referralflow.reason_codes') as $k=>$l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
    <div class="field"><label for="od-dest">{{ __('Destination') }}</label><select id="od-dest"><option value="">{{ __('Choose') }}</option></select></div>
    <button class="btn" id="od-save" type="button">{{ __('Save draft on this device') }}</button>
    <p class="hint" id="od-msg" role="status"></p>
</div>
<div class="card"><h2>{{ __('Emergency: phone the receiving facility now') }}</h2><ul id="od-phones"></ul><p class="mute" id="od-nophones" hidden>{{ __('No phone numbers saved on this device yet. They are saved when you open New referral while online.') }}</p></div>
<p><a href="{{ route('home') }}">{{ __('Try again') }}</a></p>
</main>
@endsection
