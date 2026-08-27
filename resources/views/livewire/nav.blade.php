<div>
    <a class="brandmark" href="{{ route('app.dashboard') }}" wire:navigate>
        <x-logo variant="mark" :height="30" class="brandmark__logo" />
        <div class="brandmark__text">
            <div class="brandmark__name">NexABI</div>
            <div class="brandmark__tag">Adaptive Business Intelligence</div>
        </div>
    </a>

    <nav class="nav">
        @foreach ($items as $item)
            @if (isset($item['group']))
                <div class="nav__label">{{ $item['group'] }}</div>
            @else
                @php
                    $active = request()->routeIs($item['route']);
                    $b = isset($item['badge']) ? $badges[$item['badge']] : null;
                @endphp
                <a class="nav__item {{ $active ? 'is-active' : '' }}"
                   href="{{ route($item['route']) }}" wire:navigate title="{{ $item['label'] }}">
                    <x-icon :name="$item['ico']" size="17" />
                    <span>{{ $item['label'] }}</span>
                    @if ($b && $b['n'] > 0)
                        <span class="nav__count {{ $b['cls'] }}">{{ $b['n'] > 99 ? '99+' : $b['n'] }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    <div class="sidebar__foot">
        <a class="nav__item {{ request()->routeIs('app.activity') ? 'is-active' : '' }}"
           href="{{ route('app.activity') }}" wire:navigate title="Action log">
            <x-icon name="clock" size="17" /><span>Action log</span>
        </a>
        <a class="nav__item {{ request()->routeIs('app.settings') ? 'is-active' : '' }}"
           href="{{ route('app.settings') }}" wire:navigate title="Settings">
            <x-icon name="settings" size="17" /><span>Settings</span>
        </a>

        {{-- Drives the real application: real routes, real actions, a real
             clock advance. Not a slideshow. --}}
        <button class="nav__item" data-tournament style="color:var(--brand-500)"
                title="Tournament Mode - a guided walkthrough that actually uses the product">
            <x-icon name="trophy" size="17" /><span>Tournament Mode</span>
        </button>
    </div>
</div>
