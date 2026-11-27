@extends('layouts.base')
@section('body')
<main id="main" class="login">
    <p class="brand"><img src="/mark.svg" alt="" width="34" height="34"> ReferralFlow</p>
    @yield('content')
    <form method="post" action="{{ route('locale') }}" class="langsw">@csrf
        <button class="btn quiet sm" name="locale" value="en" @if(app()->getLocale()==='en') aria-current="true" @endif>English</button>
        <button class="btn quiet sm" name="locale" value="sw" @if(app()->getLocale()==='sw') aria-current="true" @endif>Kiswahili</button>
    </form>
</main>
@endsection
