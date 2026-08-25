<div>
    <button class="searchbar" data-palette-open wire:click="openPalette" title="Search and commands (Ctrl K)">
        <x-icon name="search" size="15" />
        <span class="grow" style="text-align:left">Search or ask anything…</span>
        <span class="kbd">Ctrl K</span>
    </button>

    @if ($open)
        {{-- Keyboard first. A command palette you have to reach for the mouse
             inside is a search box wearing a palette's clothes. --}}
        <div class="scrim" wire:click.self="close" data-cmdk>
            <div class="cmdk">
                <div class="cmdk__input">
                    <x-icon name="search" size="18" />
                    <input type="text" wire:model.live.debounce.180ms="q" autofocus autocomplete="off"
                           placeholder="Search leads, properties, customers — or ask a question…">
                </div>

                <div class="cmdk__list">
                    @forelse ($groups as $group => $items)
                        <div class="cmdk__group">{{ $group }}</div>
                        @foreach ($items as $it)
                            @if (! empty($it['action']))
                                <button class="cmdk__item" wire:click="{{ $it['action'] }}">
                                    <x-icon :name="$it['ico']" size="16" />
                                    <span class="truncate">{{ $it['label'] }}</span>
                                    @if ($it['hint'])<span class="hint">{{ $it['hint'] }}</span>@endif
                                </button>
                            @else
                                <a class="cmdk__item" href="{{ $it['href'] }}" wire:navigate wire:click="close">
                                    <x-icon :name="$it['ico']" size="16" />
                                    <span class="truncate">{{ $it['label'] }}</span>
                                    @if ($it['hint'])<span class="hint">{{ $it['hint'] }}</span>@endif
                                </a>
                            @endif
                        @endforeach
                    @empty
                        <div class="empty">
                            <div class="empty__msg">
                                No matches. Try a question instead — “why is conversion falling?”
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="cmdk__foot">
                    <span><span class="kbd">↑↓</span> navigate</span>
                    <span><span class="kbd">↵</span> open</span>
                    <span><span class="kbd">esc</span> close</span>
                    <span class="spacer"></span>
                    <span class="t-3">{{ app(App\Ai\NarratorContract::class)->name() }}</span>
                </div>
            </div>
        </div>

        <script>
            /*
             * Arrow-key navigation, rebound after every Livewire re-render.
             *
             * The list is server-rendered and replaced wholesale on each
             * keystroke, so the selection cannot live in a JS variable that
             * survives the morph — it is read back off the DOM instead.
             */
            (function () {
                const scrim = document.querySelector('[data-cmdk]');
                if (!scrim || scrim.dataset.bound) return;
                scrim.dataset.bound = '1';

                const items = () => [...scrim.querySelectorAll('.cmdk__item')];

                const select = (step) => {
                    const list = items();
                    if (!list.length) return;

                    const current = list.findIndex((n) => n.classList.contains('is-sel'));
                    const index = current === -1
                        ? (step > 0 ? 0 : list.length - 1)
                        : Math.max(0, Math.min(list.length - 1, current + step));

                    list.forEach((n) => n.classList.remove('is-sel'));
                    list[index].classList.add('is-sel');
                    list[index].scrollIntoView({ block: 'nearest' });
                };

                scrim.addEventListener('keydown', (e) => {
                    if (e.key === 'ArrowDown') { e.preventDefault(); select(1); }
                    if (e.key === 'ArrowUp') { e.preventDefault(); select(-1); }
                    if (e.key === 'Enter') {
                        const sel = scrim.querySelector('.cmdk__item.is-sel') || items()[0];
                        if (sel) { e.preventDefault(); sel.click(); }
                    }
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        const host = scrim.closest('[wire\\:id]');
                        window.Livewire?.find(host?.getAttribute('wire:id'))?.call('close');
                    }
                });

                // Hovering is also selecting, so the two never disagree.
                scrim.addEventListener('mouseover', (e) => {
                    const item = e.target.closest('.cmdk__item');
                    if (!item) return;
                    items().forEach((n) => n.classList.remove('is-sel'));
                    item.classList.add('is-sel');
                });

                setTimeout(() => select(1), 60);
            })();
        </script>
    @endif
</div>
