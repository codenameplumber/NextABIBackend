<button class="btn btn--sm btn--icon" data-collapse title="Toggle sidebar" aria-label="Toggle sidebar">
    <x-icon name="panel" size="15" />
</button>

@livewire('palette')

<div class="spacer"></div>

{{-- The literal demonstration of "Adaptive": advance the business and the
     findings change on their own. --}}
@livewire('time-machine')

<div class="vdivider"></div>

{{-- The role switcher is not a permission control. It re-weights the same
     findings for a different job — a Finance Lead and a Sales Manager looking
     at one business should not be handed the same top three. --}}
<form method="POST" action="{{ route('app.role') }}" class="row-2">
    @csrf
    <select name="role" class="input t-sm" style="height:30px" onchange="this.form.submit()"
            title="View the same findings through a different lens">
        @foreach (App\Engine\InsightEngine::ROLES as $key => $r)
            <option value="{{ $key }}" @selected(session('nexabi.role', auth()->user()->role) === $key)>
                {{ $r['label'] }}
            </option>
        @endforeach
    </select>
</form>

<button class="btn btn--sm btn--icon" data-theme-toggle title="Switch theme" aria-label="Switch theme"></button>

<div class="vdivider"></div>

<div class="usermenu">
    <x-avatar :name="auth()->user()->name" size="sm" />
    <div class="usermenu__text">
        <div class="usermenu__name">{{ auth()->user()->name }}</div>
        <div class="usermenu__org">{{ app('nexabi.tenant')->name }}</div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn btn--sm btn--icon" title="Sign out" aria-label="Sign out">
            <x-icon name="logout" size="15" />
        </button>
    </form>
</div>
