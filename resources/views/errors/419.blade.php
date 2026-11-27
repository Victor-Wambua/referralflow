@extends('layouts.base')
@section('title', __('This page expired'))
@section('body')
<main id="main" class="lock"><p class="brand" style="justify-content:center"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p><h1>{{ __('This page expired') }}</h1><p>{{ __('Reload the page and try again.') }}</p><p><a class="btn" href="/">{{ __('Back to home') }}</a></p></main>
@endsection
