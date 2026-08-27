@php
    use App\Support\Fmt;
    use App\Support\Vocab;
@endphp

<div class="stack">

    <x-page-head title="Marketing"
                 :sub="Fmt::moneyShort($mk['spend']).' spent · '.Fmt::num($mk['leads']).' leads · '.number_format($mk['roas'], 2).'× commission returned per naira'">
        <x-slot:actions>
            <a class="btn btn--ghost"
               href="{{ route('app.ask', ['q' => 'Show me the worst campaigns by ROAS']) }}" wire:navigate>
                <x-icon name="ask" size="15" /><span>Ask about campaigns</span>
            </a>
        </x-slot:actions>
    </x-page-head>

    <x-page-note>
        What you spend and what comes back. **ROAS below 1× means the campaign costs more in ad spend
        than it earns back in commission** — those rows are worth a decision today.
    </x-page-note>

    <div class="grid g-4">
        <x-kpi label="Total spend" :value="$mk['spend']" format="money" ico="marketing"
               :note="count($mk['active']).' active campaigns'" />

        <x-kpi label="Cost per lead" :value="$mk['cpl']" format="moneyFull" ico="leads"
               :note="'median '.Fmt::moneyShort($mk['baselineCpl'])" invert />

        <x-kpi label="Acquisition cost" :value="$mk['cac']" format="moneyFull" ico="customers"
               help-key="cac" :note="Fmt::num($mk['won']).' customers'" invert />

        <x-kpi label="Return on spend" :value="$mk['roas']" format="x" ico="sales"
               help-key="roas" :note="Fmt::moneyShort($mk['commission']).' commission earned'" />
    </div>

    {{-- ──────────────────────────────────── where people actually came from ──
         The half that used to be missing. Word of mouth, a radio advert and a
         church announcement carry no click, so a page built only on tracked
         spend reports them as nothing — while they may be producing most of
         the customers.                                                     --}}

    <x-card title="How investors found us"
            sub="Self-reported at the enquiry and again at registration">
        @if (empty($bySource))
            <x-empty title="Nothing recorded yet"
                     msg="The question is asked on the enquiry form and at registration." />
        @else
            <div class="tablewrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th class="right">Enquiries</th>
                            <th class="right">Registered</th>
                            <th class="right">Bought</th>
                            <th class="right">Conversion</th>
                            <th class="right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bySource as $row)
                            <tr>
                                <td class="cell-primary">{{ $row['label'] }}</td>
                                <td class="right"><span class="num">{{ Fmt::num($row['leads']) }}</span></td>
                                <td class="right"><span class="num">{{ Fmt::num($row['investors']) }}</span></td>
                                <td class="right"><span class="num">{{ Fmt::num($row['sales']) }}</span></td>
                                <td class="right">
                                    <span class="num {{ $row['conversion'] >= 0.25 ? 'pos' : '' }}">
                                        {{ Fmt::pct($row['conversion'], 0) }}
                                    </span>
                                </td>
                                <td class="right">
                                    <span class="num semi">
                                        {{ $row['value'] > 0 ? Fmt::moneyShort($row['value']) : '—' }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-chart-note>
                Conversion is of people who <b>registered</b> from that channel and went on to buy.
                Enquiries are excluded from the denominator — most never become anybody s customer,
                and counting them would make every channel look broken.
            </x-chart-note>
        @endif
    </x-card>

    <livewire:area-insights area="marketing" />

    {{-- ──────────────────────────────────────────── the marketing record ──
         The half NexABI cannot see for itself.

         A Meta campaign counts its own clicks. A billboard on Lekki-Epe, a
         radio read, an announcement at a church — none of them leave a trace
         in the database, so unless somebody writes down what was spent, the
         return on the money that actually moves an agency here is not merely
         unknown, it is uncomputable.                                     --}}

    <x-card title="Marketing records"
            sub="What was run, what it was expected to produce, and what it actually did"
            flush>
        <x-slot:actions>
            @unless ($adding)
                <button class="btn btn--sm btn--primary" wire:click="addRecord">
                    <x-icon name="plus" size="14" /><span>Record marketing</span>
                </button>
            @endunless
        </x-slot:actions>

        @if ($adding)
            <form class="recform" wire:submit="save">
                <div class="recform__grid">
                    <div class="field fgrid--full">
                        <label for="mk-name">What was it</label>
                        <input id="mk-name" type="text" wire:model="name"
                               placeholder="Billboard — Lekki-Epe Expressway, Q3">
                        @error('name')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-channel">Channel</label>
                        <select id="mk-channel" wire:model="channel" class="field-select">
                            @foreach ($channels as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('channel')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-source">Who ran it</label>
                        <input id="mk-source" type="text" wire:model="source"
                               placeholder="Wazobia FM, Alpha Outdoor…">
                        @error('source')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-location">Where <span class="t-4">(optional)</span></label>
                        <input id="mk-location" type="text" wire:model="location" placeholder="Lekki">
                        @error('location')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-spend">Spend (₦)</label>
                        <input id="mk-spend" type="number" step="1000" min="0" wire:model="spend"
                               placeholder="2500000">
                        @error('spend')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-impr">Impressions <span class="t-4">(if known)</span></label>
                        <input id="mk-impr" type="number" min="0" wire:model="impressions"
                               placeholder="450000">
                        @error('impressions')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-start">Started</label>
                        <input id="mk-start" type="date" wire:model="startAt">
                        @error('startAt')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-end">Ended <span class="t-4">(optional)</span></label>
                        <input id="mk-end" type="date" wire:model="endAt">
                        @error('endAt')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-exp-l">Expected leads</label>
                        <input id="mk-exp-l" type="number" min="0" wire:model="expectedLeads"
                               placeholder="120">
                        <span class="field__hint">What you were promised when you booked it.</span>
                        @error('expectedLeads')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field">
                        <label for="mk-exp-r">Expected revenue (₦)</label>
                        <input id="mk-exp-r" type="number" step="100000" min="0"
                               wire:model="expectedRevenue" placeholder="80000000">
                        @error('expectedRevenue')<span class="field__err">{{ $message }}</span>@enderror
                    </div>

                    <div class="field fgrid--full">
                        <label for="mk-notes">Notes <span class="t-4">(optional)</span></label>
                        <textarea id="mk-notes" rows="2" wire:model="notes"
                                  placeholder="Two boards, southbound. Agency quoted 6 weeks."></textarea>
                        @error('notes')<span class="field__err">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="recform__foot">
                    <span class="t-sm t-3">
                        Record the expectation now. The outcome can be keyed in later — that is
                        what makes it measurable rather than just spent.
                    </span>
                    <span class="grow"></span>
                    <button type="button" class="btn btn--sm btn--ghost" wire:click="cancelForm">Cancel</button>
                    <button type="submit" class="btn btn--sm btn--primary">
                        <x-icon name="check" size="14" />
                        <span>{{ $editing ? 'Save changes' : 'Record it' }}</span>
                    </button>
                </div>
            </form>
        @endif

        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Marketing</th>
                        <th class="right">Spend</th>
                        <th class="right">Expected</th>
                        <th class="right">Actual</th>
                        <th class="right">Delivered</th>
                        <th class="right">Cost / lead</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $r)
                        @php
                            $delivery = $r->deliveryRate();
                            $achieved = $r->leadsAchieved();
                        @endphp

                        <tr wire:key="rec-{{ $r->id }}"
                            class="{{ $delivery !== null && $delivery < 0.6 ? 'is-flagged' : '' }}">
                            <td>
                                <div class="cell-primary">{{ $r->name }}</div>
                                <div class="t-xs t-3">
                                    {{ $channels[$r->channel] ?? $r->channel }} · {{ $r->source }}
                                    @if ($r->location) · {{ $r->location }} @endif
                                    · {{ Fmt::date($r->start_at) }}
                                    @if ($r->manual)
                                        · <span class="t-2">recorded by hand</span>
                                    @endif
                                </div>
                            </td>

                            <td class="right"><span class="num semi">{{ Fmt::moneyShort($r->spend) }}</span></td>

                            <td class="right">
                                @if ($r->expected_leads)
                                    <span class="num">{{ Fmt::num($r->expected_leads) }} leads</span>
                                    @if ($r->expected_revenue)
                                        <div class="t-xs t-3 num">{{ Fmt::moneyShort($r->expected_revenue) }}</div>
                                    @endif
                                @else
                                    <span class="t-4">—</span>
                                @endif
                            </td>

                            <td class="right">
                                {{-- Null is "nobody has counted", zero is a
                                     result. Showing both as 0 would turn a
                                     chore into a finding. --}}
                                @if ($r->manual && ! $r->isCounted())
                                    <span class="t-4">not counted yet</span>
                                @else
                                    <span class="num">{{ Fmt::num($achieved) }} leads</span>
                                    @if ($r->revenueAchieved() > 0)
                                        <div class="t-xs t-3 num">{{ Fmt::moneyShort($r->revenueAchieved()) }}</div>
                                    @endif
                                @endif
                            </td>

                            <td class="right">
                                @if ($delivery === null)
                                    <span class="t-4">—</span>
                                @else
                                    <x-badge :kind="$delivery >= 0.9 ? 'pos' : ($delivery >= 0.6 ? '' : 'neg')">
                                        {{ Fmt::pct($delivery, 0) }}
                                    </x-badge>
                                @endif
                            </td>

                            <td class="right">
                                <span class="num t-2">
                                    {{ $r->costPerLead() ? Fmt::money(round($r->costPerLead())) : '—' }}
                                </span>
                            </td>

                            <td class="right nowrap">
                                <button class="btn btn--sm btn--ghost" wire:click="count({{ $r->id }})">
                                    <span>{{ $r->isCounted() ? 'Update' : 'Record outcome' }}</span>
                                </button>
                                @if ($r->manual)
                                    <button class="btn btn--sm btn--icon" wire:click="edit({{ $r->id }})"
                                            aria-label="Edit this record">
                                        <x-icon name="settings" size="14" />
                                    </button>
                                @endif
                            </td>
                        </tr>

                        @if ($counting === $r->id)
                            <tr wire:key="count-{{ $r->id }}">
                                <td colspan="7" class="countrow">
                                    <form class="row-2 wrap" style="gap:var(--s-4)" wire:submit="saveCount">
                                        <div class="field" style="min-width:170px;margin:0">
                                            <label for="ct-l">Leads it produced</label>
                                            <input id="ct-l" type="number" min="0" wire:model="actualLeads" autofocus>
                                            @error('actualLeads')<span class="field__err">{{ $message }}</span>@enderror
                                        </div>
                                        <div class="field" style="min-width:200px;margin:0">
                                            <label for="ct-r">Revenue it produced (₦)</label>
                                            <input id="ct-r" type="number" step="100000" min="0" wire:model="actualRevenue">
                                            @error('actualRevenue')<span class="field__err">{{ $message }}</span>@enderror
                                        </div>
                                        <span class="grow"></span>
                                        <button type="button" class="btn btn--sm btn--ghost"
                                                wire:click="cancelCount">Cancel</button>
                                        <button type="submit" class="btn btn--sm btn--primary">
                                            <x-icon name="check" size="14" /><span>Save outcome</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7">
                                <x-empty title="Nothing recorded" ico="marketing"
                                         msg="Record a billboard, a radio read or a church announcement and NexABI can measure it alongside everything that tracks itself." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-chart-note>
            <b>Expected</b> is what you were promised when you booked it; <b>actual</b> is what came
            back. Keeping both is the point — forty leads is a success or a failure depending
            entirely on whether you were sold thirty or four hundred, and an agency that stores
            only the outcome can never tell which supplier oversells.
        </x-chart-note>
    </x-card>


    <div class="grid g-2">
        <x-card title="Spend by channel" sub="Where the budget goes">
            <x-chart kind="donut" :payload="$charts['spend']" />
        </x-card>

        <x-card title="Return by channel" sub="Commission earned per naira spent">
            <x-chart kind="hbar" :payload="$charts['roas']" />
            <x-chart-note>
                Orange bars sit below 1×. A channel can look busy and still be the one taking
                money off the table.
            </x-chart-note>
        </x-card>
    </div>

    <x-card title="Campaigns" :sub="count($mk['campaigns']).' campaigns · ordered by spend'" flush>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Campaign</th><th>Status</th>
                        <th class="right">Spend</th><th class="right">Leads</th>
                        <th class="right">Closed</th><th class="right">CPL</th>
                        <th class="right">ROAS</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mk['campaigns'] as $c)
                        @php
                            // Flagged where a campaign has enough leads to judge
                            // and still converts well below the portfolio median.
                            $weak = $c['leads'] >= 12 && $c['conversion'] < $mk['baselineConversion'] * 0.62;
                        @endphp
                        <tr class="{{ $weak ? 'is-flagged' : '' }}" wire:key="camp-{{ $c['id'] }}">
                            <td>
                                <div class="cell-primary">{{ $c['name'] }}</div>
                                <div class="t-xs t-3">
                                    {{ $c['channel'] }} · {{ $c['source'] }} · {{ Fmt::date($c['startAt']) }}
                                </div>
                            </td>
                            <td><x-badge :kind="Vocab::statusKind($c['status'])" dot>{{ Fmt::titleCase($c['status']) }}</x-badge></td>
                            <td class="right"><span class="num semi">{{ Fmt::moneyShort($c['spend']) }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::num($c['leads']) }}</span></td>
                            <td class="right"><span class="num">{{ Fmt::num($c['won']) }}</span></td>
                            <td class="right">
                                <span class="num {{ $c['cpl'] > $mk['baselineCpl'] * 1.8 ? 'neg' : 't-2' }}">
                                    {{ $c['leads'] ? Fmt::money(round($c['cpl'])) : '—' }}
                                </span>
                            </td>
                            <td class="right">
                                <x-badge :kind="$c['roas'] >= 3 ? 'pos' : ($c['roas'] >= 1 ? '' : 'neg')">
                                    {{ number_format($c['roas'], 2) }}×
                                </x-badge>
                            </td>
                            <td class="right">
                                @if ($c['status'] === 'active')
                                    <button class="btn btn--sm btn--ghost"
                                            wire:click="pause({{ $c['id'] }}, @js($c['name']))">
                                        <span>Pause</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty title="No campaigns" ico="marketing" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
