@php use App\Support\Fmt; @endphp

<div>
    <x-page-head title="My leads" :sub="$rows->total().' captured'">
        <x-slot:actions>
            <button class="btn btn--ghost" data-copy="{{ $realtor->questionnaireLink() }}">
                <x-icon name="plus" size="15" /><span>Copy my enquiry link</span>
            </button>
            <button class="btn btn--primary" wire:click="$toggle('adding')">
                <x-icon name="plus" size="15" />
                <span>{{ $adding ? 'Cancel' : 'Record a lead' }}</span>
            </button>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        Two ways in: **your enquiry link files itself**, and this form is for the conversation
        that happened at a wedding. Both land in the same list.
    </x-page-note>

    @if ($adding)
        <x-card title="Record a lead" sub="Someone you have spoken to yourself">
            <form wire:submit="save">
                <div class="fgrid">
                    <div class="field">
                        <label for="l-name">Name</label>
                        <input id="l-name" class="input" wire:model="name">
                        @error('name') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-phone">Phone</label>
                        <input id="l-phone" class="input" wire:model="phone" placeholder="080…">
                        @error('phone') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-email">Email <span class="hint">optional</span></label>
                        <input id="l-email" class="input" type="email" wire:model="email">
                        @error('email') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-state">State</label>
                        <select id="l-state" class="input" wire:model.live="state">
                            <option value="">Choose</option>
                            @foreach ($states as $s)
                                <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                        @error('state') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-lga">Local government</label>
                        <select id="l-lga" class="input" wire:model="lga" @disabled(! $state)>
                            <option value="">{{ $state ? 'Choose' : 'Pick a state first' }}</option>
                            @foreach ($lgas as $l)
                                <option value="{{ $l }}">{{ $l }}</option>
                            @endforeach
                        </select>
                        @error('lga') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-occ">Occupation</label>
                        <select id="l-occ" class="input" wire:model="occupation">
                            <option value="">Choose</option>
                            @foreach ($occupations as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('occupation') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-inc">Income</label>
                        <select id="l-inc" class="input" wire:model="income_bracket">
                            <option value="">Choose</option>
                            @foreach ($incomes as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('income_bracket') <span class="err">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="l-budget">Budget <span class="hint">optional</span></label>
                        <input id="l-budget" class="input" type="number" wire:model="budget"
                               step="1000000" min="0">
                    </div>

                    <div class="field">
                        <label for="l-heard">How did they hear about us?</label>
                        <select id="l-heard" class="input" wire:model="heard_about_us">
                            @foreach ($sources as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('heard_about_us') <span class="err">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:var(--s-5)">
                    <button class="btn btn--primary" type="submit"><span>Save lead</span></button>
                </div>
            </form>
        </x-card>
    @endif

    @if ($rows->isEmpty())
        <x-empty title="No leads yet"
                 msg="Share your enquiry link, or record someone you have spoken to." />
    @else
        <x-card>
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Where</th>
                            <th class="num">Budget</th>
                            <th class="num">Score</th>
                            <th>Heard via</th>
                            <th>Status</th>
                            <th>Captured</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $l)
                            <tr>
                                <td class="semi">{{ $l->name }}</td>
                                <td>
                                    {{ $l->phone ?? '—' }}
                                    @if ($l->email)<div class="t-xs t-3">{{ $l->email }}</div>@endif
                                </td>
                                <td class="t-3">{{ $l->placeLabel() ?? '—' }}</td>
                                <td class="num">{{ $l->budget ? Fmt::moneyShort($l->budget) : '—' }}</td>
                                <td class="num">{{ $l->score }}</td>
                                <td class="t-3">{{ $l->sourceLabel() ?? '—' }}</td>
                                <td>
                                    <x-badge :kind="$l->converted_user_id ? 'pos' : ($l->is_open ? 'info' : 'low')">
                                        {{ $l->converted_user_id ? 'Registered' : ($l->is_open ? 'Open' : 'Closed') }}
                                    </x-badge>
                                </td>
                                <td class="t-3">{{ Fmt::dateShort($l->created_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div style="margin-top:var(--s-5)">{{ $rows->links() }}</div>
    @endif
</div>
