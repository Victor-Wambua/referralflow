@extends('layouts.base')
@section('title', __('You do not have access'))
@section('body')
<main id="main" class="lock"><p class="brand" style="justify-content:center"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p><h1>{{ __('You do not have access') }}</h1><p>{{ __('This page is not available to your account.') }}</p><p><a class="btn" href="/">{{ __('Back to home') }}</a></p></main>
@endsection
