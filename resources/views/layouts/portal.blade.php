@php
    // Theme is stamped before any CSS parses — a flash of the wrong background
    // on a page about someone's money reads as a broken product.
    $theme = request()->cookie('nexabi_theme', 'dark');
    $customer = App\Models\Customer::find(auth()->user()->customer_id);
    $late = App\Support\Purchase::for($customer)->lateCount();
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'light' ? 'light' : 'dark' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'My property' }} — NexABI</title>
    <meta name="description" content="Your property, purchase status and next payment.">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorial.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/console.css') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    @livewireStyles
</head>
<body class="portal-body">

<header class="pt-head">
    <div class="pt-head__top">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark">
                <svg viewBox="0 0 100 100" width="18" height="18" aria-hidden="true">
                    <path d="M28 72V28h11l22 30V28h11v44H61L39 42v30z" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-text">
                <span class="brand-name">NexABI</span>
                <span class="brand-tag">Client portal</span>
            </span>
        </a>

        <div class="spacer"></div>

        <button class="btn btn--icon btn--sm" data-theme-toggle aria-label="Toggle theme"></button>

        <div class="row-2" style="padding-left:var(--s-3);margin-left:var(--s-2);border-left:1px solid var(--border)">
            <span class="agent-avatar" style="width:30px;height:30px;font-size:11px">
                {{ $customer->initials() }}
            </span>
            <div style="line-height:1.25">
                <div class="t-sm semi">{{ $customer->name }}</div>
                <div class="t-xs t-3">{{ $customer->email }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn--ghost btn--sm" title="Sign out"><span>Sign out</span></button>
        </form>
    </div>

    <nav class="pt-nav">
        @foreach ([
            ['portal.overview', 'My property', 'property'],
            ['portal.payments', 'Payments', 'finance'],
            ['portal.documents', 'Documents', 'reports'],
            ['portal.support', 'Support', 'feedback'],
        ] as [$route, $label, $ico])
            <a href="{{ route($route) }}" wire:navigate class="{{ request()->routeIs($route) ? 'is-active' : '' }}">
                <x-icon :name="$ico" size="15" />
                <span>{{ $label }}</span>
                @if ($route === 'portal.payments' && $late)
                    <span class="badge badge--neg" style="margin-left:4px"><span>{{ $late }}</span></span>
                @endif
            </a>
        @endforeach
    </nav>
</header>

<main class="pt-main">{{ $slot }}</main>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>

@livewireScripts
<script type="module" src="{{ asset('js/nexabi.js') }}"></script>
</body>
</html>
