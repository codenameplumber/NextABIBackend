<div>
    @if ($total)
        <div class="stack-2">
            <div class="between">
                <div class="row-2">
                    <x-icon name="insight" size="15" />
                    <b>
                        @if ($total === 1)
                            NexABI found something in {{ $label }}
                        @else
                            NexABI found {{ $total }} things in {{ $label }} — this is the one that matters most
                        @endif
                    </b>
                </div>

                @if ($total > 1)
                    <a class="btn btn--sm btn--ghost" href="{{ route('app.insights', ['area' => $area]) }}" wire:navigate>
                        <span>See the other {{ $total - 1 }}</span>
                    </a>
                @endif
            </div>

            {{-- No action on this card. The module screens are for reading;
                 Insights is where something gets decided. --}}
            @foreach ($items as $insight)
                <livewire:insight-card :insight="$insight" :key="$area.'-'.$insight->id" />
            @endforeach
        </div>
    @endif
</div>
