<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#36418A">
<title>@yield('title', 'ReferralFlow') · ReferralFlow</title>
<link rel="icon" href="/mark.svg" type="image/svg+xml">
<link rel="manifest" href="/manifest.webmanifest">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @auth data-idle="{{ config('referralflow.session.idle_minutes') * 60 }}" data-warn="{{ config('referralflow.session.warn_seconds') }}" @endauth>
<a class="skip" href="#main">{{ __('Skip to content') }}</a>
<div class="demo" role="note">{{ __('Synthetic demonstration data. Not for real patients.') }}@if(\App\Security\ProductionGuard::flagged()) <strong> · {{ __('NOT CLEARED FOR REAL PATIENT DATA') }}</strong>@endif</div>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8M5 10v10h14V10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></symbol>
<symbol id="i-list" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
<symbol id="i-inbox" viewBox="0 0 24 24"><path d="M3 13l3-8h12l3 8v6H3zM3 13h5l1 3h6l1-3h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
<symbol id="i-dir" viewBox="0 0 24 24"><path d="M4 21V5l8-2 8 2v16M9 21v-5h6v5M8 9h.01M12 9h.01M16 9h.01M8 13h.01M12 13h.01M16 13h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></symbol>
<symbol id="i-users" viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2M9.5 10a3.5 3.5 0 100-7 3.5 3.5 0 000 7zM21 20v-2a4 4 0 00-3-3.9M16 3.1a3.5 3.5 0 010 6.8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
<symbol id="i-gear" viewBox="0 0 24 24"><path d="M12 15a3 3 0 100-6 3 3 0 000 6zM19 12a7 7 0 00-.1-1.2l2-1.5-2-3.4-2.3.9a7 7 0 00-2-1.2L14 3h-4l-.6 2.6a7 7 0 00-2 1.2l-2.3-.9-2 3.4 2 1.5a7 7 0 000 2.4l-2 1.5 2 3.4 2.3-.9a7 7 0 002 1.2L10 21h4l.6-2.6a7 7 0 002-1.2l2.3.9 2-3.4-2-1.5c.1-.4.1-.8.1-1.2z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
<symbol id="i-bell" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1112 0c0 7 3 8 3 8H3s3-1 3-8M10 20a2 2 0 004 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="i-key" viewBox="0 0 24 24"><path d="M14 10a4 4 0 11-1-2.6L21 15v3h-3v-2h-2v-2h-2.4M8 10h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="i-doc" viewBox="0 0 24 24"><path d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
</defs></svg>
<div id="live" class="sr" aria-live="polite" role="status"></div>
@yield('body')
<dialog id="idle-dialog" aria-labelledby="idle-h"><h2 id="idle-h">{{ __('Still there?') }}</h2><p>{{ __('For privacy you will be signed out in') }} <strong id="idle-count">60</strong> {{ __('seconds.') }}</p><div class="btn-row"><button class="btn" id="idle-stay" type="button">{{ __('Stay signed in') }}</button></div></dialog>
</body>
</html>
