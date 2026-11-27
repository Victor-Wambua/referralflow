@extends('layouts.base')
@section('title', __('Page not found'))
@section('body')
<main id="main" class="lock"><p class="brand" style="justify-content:center"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p><h1>{{ __('Page not found') }}</h1><p>{{ __('We could not find that page.') }}</p><p><a class="btn" href="/">{{ __('Back to home') }}</a></p></main>
@endsection
