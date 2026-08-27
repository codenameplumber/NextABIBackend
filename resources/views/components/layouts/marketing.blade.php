@props([
    'title' => 'NexABI — Business intelligence for real estate',
    'description' => 'NexABI reads every lead, sale and instalment across your real-estate business, finds what is going wrong, and hands you the decision with a button that changes the data.',
    'bodyClass' => 'editorial',
    'chrome' => true,
])
@php
    // Stamped before any CSS parses. Doing this in JS after load produces a
    // flash of the wrong background, which reads as a broken product.
    $theme = request()->cookie('nexabi_theme', 'light');
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorial.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/icons.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/icons.png') }}">

    @livewireStyles
</head>
<body class="{{ $bodyClass }}">

@if ($chrome)
    <header class="pub-head">
        <div class="pub-head__in">
            <a class="brand" href="{{ route('home') }}" wire:navigate>
                <x-logo variant="mark" :height="30" />
                <span class="brand-text">
                    <span class="brand-name">NexABI</span>
                </span>
            </a>

            <nav class="pub-nav">
                <a href="{{ route('properties') }}" wire:navigate
                   class="{{ request()->routeIs('properties', 'property') ? 'is-active' : '' }}">Properties</a>
                <a href="{{ route('enquire') }}" wire:navigate
                   class="{{ request()->routeIs('enquire') ? 'is-active' : '' }}">Talk to us</a>
                <a href="{{ route('realtor.apply') }}">Become a realtor</a>
            </nav>

            <span class="spacer"></span>

            <button class="btn btn--icon btn--sm" data-theme-toggle aria-label="Toggle theme"></button>

            @if ($user)
                <a class="btn btn--primary btn--sm" href="{{ route($user->homeRoute()) }}">
                    <span>My account</span>
                </a>
            @else
                <a class="btn btn--ghost btn--sm" href="{{ route('login') }}">Sign in</a>
                <a class="btn btn--primary btn--sm" href="{{ route('register') }}">Get started</a>
            @endif
        </div>
    </header>
@endif

{{ $slot }}

@if ($chrome)
    <footer class="pub-foot">
        <div class="pub-foot__in">
            <div class="pub-foot__brand">
                {{-- The full lockup, here and only here in the public chrome.
                     The wordmark is built for a dark ground — its "ABI" is a
                     pale silver that all but vanishes on white — so it goes
                     where the ground is black and the mark alone carries the
                     brand everywhere else. --}}
                <a class="brand" href="{{ route('home') }}" aria-label="NexABI home">
                    <x-logo :height="46" />
                </a>

                <p>
                    Adaptive business intelligence for property. It finds what is going wrong,
                    tells you what to do about it, and runs the business it reads.
                </p>

                <div class="pub-foot__badge">
                    <span class="dot"></span>
                    <span>Detectors sweeping every 15 minutes</span>
                </div>
            </div>

            <nav class="pub-foot__cols" aria-label="Footer">
                <div>
                    <h4>Product</h4>
                    <a href="{{ route('deck') }}">How it works</a>
                    <a href="{{ route('deck.technical') }}">Technical appendix</a>
                    <a href="{{ route('login') }}">Sign in</a>
                </div>
                <div>
                    <h4>Buying</h4>
                    <a href="{{ route('properties') }}">Browse properties</a>
                    <a href="{{ route('enquire') }}">Make an enquiry</a>
                    <a href="{{ route('register') }}">Create an account</a>
                </div>
                <div>
                    <h4>Selling</h4>
                    <a href="{{ route('realtor.apply') }}">Become a realtor</a>
                    <a href="{{ route('login') }}">Realtor sign in</a>
                </div>
            </nav>
        </div>

        <div class="pub-foot__base">
            <span>&copy; {{ now()->year }} NexABI · Lagos, Nigeria</span>
            <span class="grow"></span>
            <span>Built for the Nigerian property market</span>
        </div>
    </footer>
@endif

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>

@livewireScripts
<script type="module" src="{{ asset('js/nexabi.js') }}"></script>
{{-- Self-initialising and idempotent: it re-scans after a wire:navigate, so a
     carousel arriving on a soft navigation still works. --}}
<script type="module" src="{{ asset('js/carousel.js') }}"></script>
</body>
</html>
