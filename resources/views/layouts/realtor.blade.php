@php
    $theme = request()->cookie('nexabi_theme', 'light');
    $realtor = App\Models\Agent::find(auth()->user()->agent_id);
    $approved = (bool) $realtor?->isApproved();
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme === 'dark' ? 'dark' : 'light' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Realtor' }} — NexABI</title>
    <meta name="description" content="Your leads, your properties and your commission.">

    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/editorial.css') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}">
    <link rel="stylesheet" href="{{ asset('css/console.css') }}">
    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
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
                <span class="brand-tag">Realtor</span>
            </span>
        </a>

        <div class="spacer"></div>

        @if ($approved && $realtor->code)
            {{-- The code is the realtor's whole livelihood, so it lives in the
                 chrome rather than on one page they have to navigate to. --}}
            <button class="code-chip" data-copy="{{ $realtor->registrationLink() }}"
                    title="Copy your registration link">
                <span class="code-chip__label">My code</span>
                <span class="code-chip__val">{{ $realtor->code }}</span>
                <x-icon name="plus" size="13" />
            </button>
        @endif

        <button class="btn btn--icon btn--sm" data-theme-toggle aria-label="Toggle theme"></button>

        <div class="row-2" style="padding-left:var(--s-3);margin-left:var(--s-2);border-left:1px solid var(--border)">
            <span class="agent-avatar" style="width:30px;height:30px;font-size:11px">
                {{ $realtor?->initials() }}
            </span>
            <div style="line-height:1.25">
                <div class="t-sm semi">{{ $realtor?->name }}</div>
                <div class="t-xs t-3">{{ $approved ? 'Approved realtor' : 'Awaiting approval' }}</div>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn--ghost btn--sm" title="Sign out"><span>Sign out</span></button>
        </form>
    </div>

    @if ($approved)
        <nav class="pt-nav">
            @foreach ([
                ['realtor.dashboard', 'Dashboard', 'dashboard'],
                ['realtor.ask', 'Ask NexABI', 'ask'],
                ['realtor.leads', 'My leads', 'leads'],
                ['realtor.properties', 'Properties', 'property'],
                ['realtor.materials', 'Materials', 'marketing'],
                ['realtor.commission', 'Commission', 'finance'],
            ] as [$route, $label, $ico])
                <a href="{{ route($route) }}" wire:navigate
                   class="{{ request()->routeIs($route) ? 'is-active' : '' }}">
                    <x-icon :name="$ico" size="15" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    @endif
</header>

<main class="pt-main">{{ $slot }}</main>

<div id="toasts" class="toasts" role="status" aria-live="polite"></div>

@livewireScripts
<script type="module" src="{{ asset('js/nexabi.js') }}"></script>
</body>
</html>
