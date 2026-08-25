@php
    use App\Livewire\Portal\Support;
    use App\Support\Fmt;
@endphp

<div>
    <h1 class="display--sm">Support</h1>

    <div class="page-note pt-note">
        <x-icon name="info" size="15" />
        <span>
            Anything you raise here goes to your agent first, and escalates to the operations team
            automatically if it is not answered within two working days.
        </span>
    </div>

    {{-- ───────────────────────────────────────────────── agent contact ── --}}

    @if ($p->agent)
        <div class="agent-card">
            <span class="agent-avatar">{{ Fmt::initials($p->agent->name) }}</span>
            <div class="grow">
                <div class="t-xs t-3">Your agent</div>
                <div class="semi" style="margin-top:2px">{{ $p->agent->name }}</div>
                <div class="t-sm t-3">{{ $p->agent->role }} · {{ $p->agent->team }} team</div>
            </div>
            <div class="row-2">
                <span class="badge badge--pos" title="Typical first response">
                    <x-icon name="clock" size="11" /><span>Replies within a day</span>
                </span>
            </div>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────── raise an issue ── --}}

    <form class="card" id="raise" wire:submit="send">
        <div class="card__head">
            <div>
                <div class="card__title">Raise an issue</div>
                <div class="card__sub">Goes straight to your agent — usually answered within a day</div>
            </div>
        </div>

        <div class="card__body">
            <div class="form-grid">
                <div class="field">
                    <label for="topic">What is this about?</label>
                    <select id="topic" wire:model="topic">
                        @foreach (Support::TOPICS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="field__hint">
                        Picking the right one gets it to the right person faster.
                    </span>
                </div>

                <div class="field">
                    <label for="body">What has happened?</label>
                    <textarea id="body" rows="5" wire:model="body"
                              placeholder="Dates, amounts and names help. If it is about the property, photographs help most of all."></textarea>
                    @error('body')
                        <span class="field__hint" style="color:var(--neg)">{{ $message }}</span>
                    @else
                        <span class="field__hint">
                            A sentence or two is plenty — at least 15 characters.
                        </span>
                    @enderror
                </div>
            </div>

            <div class="form-foot">
                <button class="btn btn--primary btn--lg" type="submit"
                        wire:loading.attr="disabled" wire:target="send">
                    <x-icon name="feedback" size="15" />
                    <span wire:loading.remove wire:target="send">Send to my agent</span>
                    <span wire:loading wire:target="send">Sending…</span>
                </button>
                <span class="t-sm t-3">
                    Saved against your account — your agent sees it in the console immediately.
                </span>
            </div>
        </div>
    </form>

    {{-- ──────────────────────────────────────────────── open requests ── --}}

    @if ($tickets->isNotEmpty())
        <div style="margin-top:var(--s-5)">
            <div class="pt-sec">
                <div>
                    <h2>Your requests</h2>
                    <p>{{ $tickets->count() }} raised on this account</p>
                </div>
            </div>

            @foreach ($tickets as $t)
                <div class="ticket" wire:key="tkt-{{ $t->id }}">
                    <div class="ticket__h">
                        <span class="ticket__t">{{ $t->topic }}</span>
                        <x-badge :kind="$t->status === 'open' ? 'medium' : 'pos'" dot>
                            {{ Fmt::titleCase($t->status) }}
                        </x-badge>
                        <span class="spacer"></span>
                        <span class="t-xs t-4 mono">{{ $t->reference }}</span>
                    </div>

                    <div class="ticket__b">{{ $t->body }}</div>

                    @if ($t->reply)
                        <div class="ticket__reply">
                            <b class="t-sm">
                                {{ $p->agent?->name ?? 'Your agent' }} · auto-acknowledgement
                            </b>
                            <div style="margin-top:4px">{{ $t->reply }}</div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ────────────────────────────────────── feedback already on file ── --}}

    @if ($feedback->isNotEmpty())
        <div>
            <div class="pt-sec">
                <div>
                    <h2>Feedback you have given us</h2>
                    <p>What you told us after your purchase</p>
                </div>
            </div>

            <div class="card">
                @foreach ($feedback as $f)
                    @php
                        $tone = $f->rating >= 4 ? 'pos' : ($f->rating <= 2 ? 'neg' : null);
                    @endphp
                    <div class="doc-row" style="align-items:flex-start" wire:key="fb-{{ $f->id }}">
                        <div class="doc-icon"
                             style="background:var({{ $tone ? "--{$tone}-soft" : '--surface-3' }});
                                    color:var({{ $tone ? "--{$tone}" : '--text-2' }})">
                            <x-icon name="feedback" size="15" />
                        </div>
                        <div class="grow">
                            <div class="row-2" style="margin-bottom:4px">
                                <span class="semi" style="color:var({{ $tone ? "--{$tone}" : '--text-2' }})">
                                    {{ str_repeat('★', $f->rating).str_repeat('☆', 5 - $f->rating) }}
                                </span>
                                <span class="t-xs t-4">{{ $f->channel }} · {{ Fmt::date($f->created_at) }}</span>
                            </div>
                            <div class="quote">{{ $f->text }}</div>
                            <div class="row-2" style="margin-top:8px">
                                @foreach ($f->tags ?? [] as $tag)<x-badge>{{ $tag }}</x-badge>@endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ───────────────────────────────────────────── common questions ── --}}

    <div>
        <div class="pt-sec">
            <div><h2>Common questions</h2><p>Answered before you have to ask</p></div>
        </div>

        <div class="card">
            <div class="card__body faq">
                @foreach ([
                    'How quickly will someone get back to me?' =>
                        'Your agent is the first responder and usually replies within one working day. Anything unanswered after two working days escalates to the operations team automatically.',
                    'Can I change my payment plan?' =>
                        'Yes. Choose "Change my payment plan" above and say what you need. Your agent will propose a revised schedule over the same total — this is a routine request.',
                    'When do I get the title documents?' =>
                        "The deed of assignment is executed once the purchase price is fully settled. Governor's consent and the C of O follow and typically take 60–90 days after that. The Documents tab shows exactly what each item is waiting on.",
                    'Who do I contact about a defect in the property?' =>
                        'Raise it here under "Something about the property". Include photographs if you can — it is the single thing that most speeds up a resolution.',
                    'Can someone else act on my behalf?' =>
                        'Yes, with a written authority. Raise a request under "Something else" and we will send the form.',
                ] as $q => $a)
                    <details>
                        <summary>{{ $q }}</summary>
                        <p>{{ $a }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</div>
