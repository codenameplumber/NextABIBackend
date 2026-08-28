@php
    // Theme is read from the cookie and stamped on <html> before any CSS
    // parses. Doing it in JS after load produces a flash of the wrong
    // background on every navigation, which reads as a broken product.
    $theme = request()->cookie('nexabi_theme', 'light');
    $rail = request()->cookie('nexabi_rail') === '1';
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Console' }} · NexABI</title>
    <meta name="description" content="AI-powered business intelligence for real estate. Data to insight to action.">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/views.css') }}">
    <link rel="stylesheet" href="{{ asset('css/console.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/icons.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/icons.png') }}">

    @livewireStyles
</head>
<body>
    <div id="app" class="app {{ $rail ? 'is-collapsed' : '' }}">
        {{-- Below 900px the sidebar leaves the flow and slides over the page,
             so it needs something to close it that is not the menu button.
             A real element rather than a pseudo, because it has to be
             clickable and reachable by keyboard. --}}
        <button class="nav-scrim" data-nav-close tabindex="-1" aria-label="Close menu"></button>

        <aside class="sidebar">
            @livewire('nav')
        </aside>

        <div class="main">
            <header class="topbar">
                @include('partials.topbar')
            </header>

            <main class="view" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>

    <div id="overlays"></div>
    <div id="toasts" class="toasts" role="status" aria-live="polite"></div>

    @livewireScripts
    <script type="module" src="{{ asset('js/nexabi.js') }}"></script>
    <script type="module" src="{{ asset('js/tournament.js') }}"></script>
</body>
</html>
