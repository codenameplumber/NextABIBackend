@php use App\Support\Fmt; @endphp

{{--
    Advancing does not just move the date — it simulates the business that
    would have happened: leads arrive, deals close, instalments fall due, and
    neglected leads go cold. Which is exactly why clearing the stale backlog
    first genuinely changes what comes back.
--}}
<div class="row-2"
     style="padding:3px 4px 3px 10px;border-radius:var(--r-md);
            border:1px solid var(--border);background:var(--surface)">

    <x-icon name="clock" size="14" />

    <span class="t-sm num" style="color:var(--text-2);font-weight:550"
          title="The business clock">{{ Fmt::date(now()) }}</span>

    <button class="btn btn--sm" wire:click="advance(7)"
            wire:loading.attr="disabled" wire:target="advance"
            title="Advance the business 7 days — leads arrive, deals close, instalments fall due">
        <span wire:loading.remove wire:target="advance">+7 days</span>
        <span wire:loading wire:target="advance">Simulating…</span>
    </button>

    <button class="btn btn--sm" wire:click="advance(30)"
            wire:loading.attr="disabled" wire:target="advance"
            title="Advance the business 30 days">
        <span wire:loading.remove wire:target="advance">+30</span>
        <span wire:loading wire:target="advance">…</span>
    </button>
</div>
