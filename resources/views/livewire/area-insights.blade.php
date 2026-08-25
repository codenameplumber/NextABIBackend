<div>
    @if ($total)
        <div class="stack-2">
            <div class="between">
                <div class="row-2">
                    <x-icon name="insight" size="15" />
                    <b>NexABI found {{ $total }} thing{{ $total > 1 ? 's' : '' }} in {{ $label }}</b>
                </div>
                <a class="btn btn--sm btn--ghost" href="{{ route('app.insights', ['area' => $area]) }}" wire:navigate>
                    <span>All insights</span>
                </a>
            </div>

            @foreach ($items as $insight)
                <livewire:insight-card :insight="$insight" :key="$area.'-'.$insight->id" />
            @endforeach
        </div>
    @endif
</div>
