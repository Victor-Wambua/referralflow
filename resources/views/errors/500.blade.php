@extends('layouts.base')
@section('title', __('Something went wrong'))
@section('body')
<main id="main" class="lock"><p class="brand" style="justify-content:center"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p><h1>{{ __('Something went wrong') }}</h1><p>{{ __('The problem has been logged. No patient information is shown on this page.') }}</p><p><a class="btn" href="/">{{ __('Back to home') }}</a></p></main>
@endsection
