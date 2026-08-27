@php
    // Theme is stamped before any CSS parses — a flash of the wrong background
    // on a page about someone's money reads as a broken product.
    $theme = request()->cookie('nexabi_theme', 'light');
    $customer = App\Models\Customer::find(auth()->user()->customer_id);
    $late = App\Support\Purchase::for($customer)->lateCount();
    $myRealtor = $customer?->realtor;
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}">
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
    <link rel="icon" type="image/png" href="{{ asset('img/icons.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/icons.png') }}">

    @livewireStyles
</head>
<body class="portal-body">

<header class="pt-head">
    <div class="pt-head__top">
        <a class="brand" href="{{ route('home') }}">
            <x-logo variant="mark" :height="30" />
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
            ['portal.browse', 'Browse', 'search'],
            ['portal.ask', 'Ask NexABI', 'ask'],
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

@if ($myRealtor)
    {{-- Always visible, on every page.

         A buyer browsing a ₦90m flat has questions the software cannot answer
         — is the road tarred, what are the service charges, can I see it on
         Saturday. Putting the person who CAN answer behind a Support tab
         means the question gets asked to nobody. --}}
    <div class="realtor-bar">
        <div class="realtor-bar__in">
            <x-avatar :name="$myRealtor->name" />

            <div class="realtor-bar__who">
                <span class="k">Your realtor</span>
                <span class="v">{{ $myRealtor->name }}</span>
            </div>

            <span class="spacer"></span>

            @if ($myRealtor->phone)
                <a class="btn btn--sm btn--ghost" href="tel:{{ preg_replace('/\s+/', '', $myRealtor->phone) }}">
                    <x-icon name="agent" size="13" /><span>{{ $myRealtor->phone }}</span>
                </a>
            @endif

            <a class="btn btn--sm btn--ghost" href="mailto:{{ $myRealtor->email }}">
                <x-icon name="mail" size="13" /><span class="realtor-bar__mail">{{ $myRealtor->email }}</span>
            </a>

            <a class="btn btn--sm btn--primary" href="{{ route('portal.support') }}" wire:navigate>
                <x-icon name="send" size="13" /><span>Ask a question</span>
            </a>
        </div>
    </div>
@endif

<main class="pt-main">{{ $slot }}</main>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>

@livewireScripts
<script type="module" src="{{ asset('js/nexabi.js') }}"></script>
</body>
</html>
