@extends('layouts.base')
@section('title', __('Too many attempts'))
@section('body')
<main id="main" class="lock"><p class="brand" style="justify-content:center"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p><h1>{{ __('Too many attempts') }}</h1><p>{{ __('Please wait a minute and try again.') }}</p><p><a class="btn" href="/">{{ __('Back to home') }}</a></p></main>
@endsection
