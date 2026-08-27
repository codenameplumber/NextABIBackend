@php use App\Support\Fmt; @endphp

<div class="stack">
    <x-page-head title="Promotional materials"
                 :sub="$rows->count().' published · every realtor gets their own version'">
        <x-slot:actions>
            @unless ($adding)
                <button class="btn btn--primary" wire:click="add">
                    <x-icon name="plus" size="15" /><span>Add a material</span>
                </button>
            @endunless
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Upload it once. **Each realtor downloads it with their own name, number and registration
        link already attached**, so a corrected price is fixed in one place rather than a hundred.
    </x-page-note>

    @if ($adding)
        <x-card :title="$editing ? 'Edit material' : 'New material'"
                :sub="$needsFile ? 'Artwork realtors can send as it is' : 'A message, personalised per realtor'"
                flush>
            <form class="recform" wire:submit="save">
                <div class="recform__grid">
                    <div class="field fgrid--full">
                        <label for="m-title">Title</label>
                        <input id="m-title" type="text" wire:model="title"
                               placeholder="Lekki 3-bed launch — flyer">
                        @error('title')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="m-kind">Kind</label>
                        {{-- Live, because the kind decides whether the form asks
                             for a file or for words. --}}
                        <select id="m-kind" class="field-select" wire:model.live="kind">
                            @foreach ($kinds as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('kind')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="m-prop">About a property <span class="t-4">(optional)</span></label>
                        <select id="m-prop" class="field-select" wire:model="property_id">
                            <option value="">Not property-specific</option>
                            @foreach ($properties as $p)
                                <option value="{{ $p->id }}">{{ $p->title }} · {{ $p->ref }}</option>
                            @endforeach
                        </select>
                        @error('property_id')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field fgrid--full">
                        <label for="m-body">
                            {{ $needsFile ? 'Caption' : 'The message' }}
                            @if ($needsFile) <span class="t-4">(optional)</span> @endif
                        </label>
                        <textarea id="m-body" rows="{{ $needsFile ? 2 : 7 }}" wire:model="body"
                                  placeholder="Hi — I'm {first_name}, and this one is still available…"></textarea>
                        @error('body')<span class="field__err">{{ $message }}</span>@enderror

                        {{-- Listed here rather than documented elsewhere. A
                             typo'd placeholder ships "call me on {phone}" to a
                             few hundred prospects. --}}
                        <span class="field__hint">
                            Replaced per realtor:
                            <code>{realtor}</code> <code>{first_name}</code>
                            <code>{phone}</code> <code>{code}</code> <code>{link}</code>
                        </span>
                    </div>
                </div>

                @if ($needsFile)
                    <div class="imgpick">
                        <div class="eyebrow">The artwork</div>

                        <div class="imgpick__grid">
                            @if ($asset)
                                <figure class="imgpick__it is-new">
                                    @if (method_exists($asset, 'temporaryUrl') && str_starts_with((string) $asset->getMimeType(), 'image/'))
                                        <img src="{{ $asset->temporaryUrl() }}" alt="">
                                    @else
                                        <div class="imgpick__doc"><x-icon name="doc" size="22" /></div>
                                    @endif
                                    <figcaption>ready to save</figcaption>
                                </figure>
                            @elseif ($existingFile)
                                <figure class="imgpick__it">
                                    <img src="{{ \App\Support\PropertyPhotos::resolve($existingFile) }}" alt="">
                                    <figcaption>current</figcaption>
                                </figure>
                            @else
                                <p class="t-sm t-3" style="margin:0">
                                    A flyer with no artwork is just a title. Pick the file realtors
                                    will actually be sending.
                                </p>
                            @endif
                        </div>

                        <div class="imgpick__controls">
                            <label class="btn btn--sm btn--ghost">
                                <x-icon name="upload" size="13" />
                                <span>{{ $existingFile ? 'Replace file' : 'Choose a file' }}</span>
                                <input type="file" wire:model="asset" hidden
                                       accept="image/*,application/pdf,video/mp4,video/webm">
                            </label>
                            <span class="t-sm t-3" wire:loading wire:target="asset">Uploading…</span>
                            <span class="t-sm t-4">JPG, PNG, WebP, GIF, PDF or MP4 · up to 20MB</span>
                        </div>

                        @error('asset')<span class="field__err">{{ $message }}</span>@enderror
                    </div>
                @endif

                <div class="recform__foot">
                    <span class="grow"></span>
                    <button type="button" class="btn btn--sm btn--ghost" wire:click="cancel">Cancel</button>
                    <button type="submit" class="btn btn--sm btn--primary"
                            wire:loading.attr="disabled" wire:target="save, asset">
                        <x-icon name="check" size="14" />
                        <span>{{ $editing ? 'Save changes' : 'Publish' }}</span>
                    </button>
                </div>
            </form>
        </x-card>
    @endif

    @if ($rows->isEmpty())
        <x-empty title="Nothing published yet" ico="doc"
                 msg="Realtors can still share their own registration link — this gives them something to send with it." />
    @else
        {{-- A grid of what the things look like, not a table of filenames.
             Picking which flyer to send is a visual decision, and a list of
             titles makes it one nobody can make without opening every row. --}}
        <div class="matgrid">
            @foreach ($rows as $m)
                <article class="matcard {{ $m->active ? '' : 'is-off' }}" wire:key="mat-{{ $m->id }}">
                    <div class="matcard__art">
                        @if ($m->isPreviewable())
                            <img src="{{ $m->assetUrl() }}" alt="{{ $m->title }}" loading="lazy">
                        @elseif ($m->hasFile())
                            {{-- A PDF or a clip: no inline preview, so say what
                                 it is rather than showing a broken image. --}}
                            <div class="matcard__doc">
                                <x-icon name="doc" size="26" />
                                <span>{{ strtoupper(pathinfo($m->file_path, PATHINFO_EXTENSION)) }}</span>
                            </div>
                        @else
                            <div class="matcard__doc">
                                <x-icon name="mail" size="26" />
                                <span>Message</span>
                            </div>
                        @endif

                        <span class="matcard__kind">{{ $m->kindLabel() }}</span>
                    </div>

                    <div class="matcard__b">
                        <h3 class="matcard__t">{{ $m->title }}</h3>
                        <div class="t-xs t-3">
                            {{ $m->property?->title ?? 'General' }} ·
                            {{ Fmt::num($m->downloads) }} {{ $m->downloads === 1 ? 'download' : 'downloads' }}
                        </div>

                        @if ($m->body)
                            <p class="matcard__body">{{ \Illuminate\Support\Str::limit($m->body, 110) }}</p>
                        @endif
                    </div>

                    <footer class="matcard__foot">
                        <x-badge :kind="$m->active ? 'pos' : 'low'" dot>
                            {{ $m->active ? 'Published' : 'Withdrawn' }}
                        </x-badge>

                        <span class="grow"></span>

                        @if ($m->hasFile())
                            <a class="btn btn--sm btn--icon" href="{{ $m->assetUrl() }}"
                               target="_blank" rel="noopener" title="Open the file" aria-label="Open the file">
                                <x-icon name="eye" size="14" />
                            </a>
                        @endif

                        <button class="btn btn--sm btn--icon" wire:click="edit({{ $m->id }})"
                                title="Edit" aria-label="Edit">
                            <x-icon name="settings" size="14" />
                        </button>

                        <button class="btn btn--sm btn--ghost" wire:click="toggle({{ $m->id }})">
                            <span>{{ $m->active ? 'Withdraw' : 'Publish' }}</span>
                        </button>

                        <button class="btn btn--sm btn--icon" wire:click="destroy({{ $m->id }})"
                                wire:confirm="Delete {{ $m->title }}? The file goes with it and this cannot be undone."
                                title="Delete" aria-label="Delete">
                            <x-icon name="x" size="14" />
                        </button>
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
</div>
