@php
    use App\Engine\InsightEngine;
    use App\Support\Fmt;
    use App\Support\Vocab;
@endphp

<div class="stack">

    <x-page-head title="Settings" sub="Appearance, your role, data and provenance" />

    <x-page-note>
        Appearance, your role, and the data controls. This is also where you **import a CSV** —
        the fastest way to prove nothing in this product is hardcoded.
    </x-page-note>

    @if (session('status'))
        <div class="page-note"><x-icon name="check" size="15" /><span>{{ session('status') }}</span></div>
    @endif

    {{-- ----------------------------------------------------- appearance -- --}}

    <x-card title="Appearance">
        <div class="stack-4">
            <div class="between" style="align-items:flex-start;gap:var(--s-5)">
                <div style="max-width:58ch">
                    <div class="semi">Theme</div>
                    <div class="t-sm t-3" style="margin-top:3px;line-height:1.55">
                        Both modes use separately validated colour steps — the dark palette is chosen,
                        not auto-lightened from the light one.
                    </div>
                </div>
                <div class="row-2" style="flex:none">
                    <button class="btn" data-theme-toggle>
                        <span>Switch theme</span>
                    </button>
                </div>
            </div>

            <div class="between" style="align-items:flex-start;gap:var(--s-5)">
                <div style="max-width:58ch">
                    <div class="semi">Role</div>
                    <div class="t-sm t-3" style="margin-top:3px;line-height:1.55">
                        {{ InsightEngine::ROLES[session('nexabi.role', auth()->user()->role)]['hint'] }}.
                        Switching role re-ranks findings; it never hides them.
                    </div>
                </div>
                <form method="POST" action="{{ route('app.role') }}" class="row-2" style="flex:none">
                    @csrf
                    <select name="role" class="input" onchange="this.form.submit()">
                        @foreach (InsightEngine::ROLES as $key => $r)
                            <option value="{{ $key }}" @selected(session('nexabi.role', auth()->user()->role) === $key)>
                                {{ $r['label'] }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </x-card>

    {{-- ------------------------------------------------------ ingestion -- --}}

    <x-card title="Data ingestion"
            sub="Import records and watch the metrics, detectors and findings respond">
        <div class="stack-4">

            <label class="drop-zone" style="display:block;cursor:pointer">
                <div class="center" style="margin-bottom:10px"><x-icon name="upload" size="22" /></div>
                <div class="semi">
                    @if ($csv)
                        {{ $csv->getClientOriginalName() }}
                    @else
                        Choose a CSV of leads
                    @endif
                </div>
                <div class="t-sm t-3" style="margin-top:6px">
                    Column names are matched loosely — name, email, source, status, score, budget,
                    location, type, createdAt, lastContact.
                </div>
                <input type="file" accept=".csv,text/csv" wire:model="csv" hidden>
            </label>

            @error('csv')<span class="field__error">{{ $message }}</span>@enderror

            <div wire:loading wire:target="csv" class="t-sm t-3">Reading the file…</div>

            <div class="row-2 wrap">
                <button class="btn btn--primary" wire:click="import" @disabled(! $csv)
                        wire:loading.attr="disabled" wire:target="import">
                    <x-icon name="upload" size="15" />
                    <span wire:loading.remove wire:target="import">Import and re-detect</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </button>

                <button class="btn btn--ghost btn--sm" wire:click="template">
                    <x-icon name="download" size="13" /><span>Download lead template</span>
                </button>

                <button class="btn btn--ghost btn--sm" wire:click="generate(40)"
                        wire:loading.attr="disabled" wire:target="generate">
                    <x-icon name="plus" size="13" />
                    <span wire:loading.remove wire:target="generate">Generate 40 synthetic leads</span>
                    <span wire:loading wire:target="generate">Generating…</span>
                </button>
            </div>

            @if ($importLog)
                <div class="stack-2">
                    <div class="eyebrow">Imported this session</div>
                    @foreach (array_reverse($importLog) as $i)
                        <div class="row-2">
                            <x-icon name="check" size="14" />
                            <span class="t-sm">{{ $i['file'] }} — {{ Fmt::num($i['rows']) }} rows into leads</span>
                            <span class="spacer"></span>
                            <span class="t-xs t-4">{{ $i['at'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-card>

    {{-- ------------------------------------------------------- policies -- --}}

    <x-card title="Policies in force"
            sub="Set by accepting recommendations. These are stored decisions, not settings you configure here."
            :flush="(bool) count($policies)">
        @if (count($policies))
            <div class="tablewrap">
                <table class="table">
                    <thead><tr><th>Policy</th><th class="right">Value</th></tr></thead>
                    <tbody>
                        @foreach ($policies as $k => $v)
                            <tr>
                                <td><span class="mono t-sm">{{ $k }}</span></td>
                                <td class="right">
                                    <x-badge kind="ai">
                                        {{ is_bool($v) ? ($v ? 'on' : 'off') : (is_scalar($v) ? $v : json_encode($v)) }}
                                    </x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty title="No policies set" ico="check"
                     msg="Accept a policy recommendation from an insight card and it will appear here." />
        @endif
    </x-card>

    {{-- ----------------------------------------------------- AI provider -- --}}

    <x-card title="Intelligence layer">
        <div class="stack-4">
            <div class="between" style="align-items:flex-start;gap:var(--s-5)">
                <div style="max-width:58ch">
                    <div class="semi">Language provider</div>
                    <div class="t-sm t-3" style="margin-top:3px;line-height:1.55">
                        Analysis, detection, scoring and actions run against your database and are
                        fully real. Only prose generation sits behind this seam.
                    </div>
                </div>
                <div class="row-2" style="flex:none">
                    <x-badge kind="ai">{{ $narrator->name() }}</x-badge>
                    <x-badge :kind="$narrator->isLive() ? 'pos' : ''">
                        {{ $narrator->isLive() ? 'Live model' : 'Deterministic' }}
                    </x-badge>
                </div>
            </div>

            <div class="t-sm t-3" style="line-height:1.7">
                Swapping providers is a config change, not a code change. Set
                <span class="mono t-2">OPENAI_API_KEY</span> and
                <span class="mono t-2">App\Ai\OpenAiNarrator</span> takes over; leave it blank and
                <span class="mono t-2">App\Ai\TemplateNarrator</span> answers instead. Either way the
                model receives <span class="mono t-2">insight.facts</span> — figures already computed
                by the metrics layer — and never raw rows, so it is structurally incapable of
                inventing a number. Every call falls back to the templated narrator on failure: an
                AI outage should degrade the prose, not the product.
            </div>
        </div>
    </x-card>

    {{-- --------------------------------------------- planted narratives -- --}}

    <x-card title="Planted conditions in this dataset"
            sub="The simulation is biased so the data genuinely contains these conditions. The detectors are not told about them — they find them independently. Change the data and the corresponding finding changes or disappears."
            flush>
        <div class="tablewrap">
            <table class="table">
                <thead>
                    <tr><th>Condition</th><th>Area</th><th>Detector that finds it</th><th>What was planted</th></tr>
                </thead>
                <tbody>
                    @foreach (Vocab::NARRATIVES as $key => $n)
                        <tr>
                            <td><span class="cell-primary">{{ Fmt::titleCase($key) }}</span></td>
                            <td><span class="t-2">{{ InsightEngine::AREAS[$n['area']] ?? $n['area'] }}</span></td>
                            <td><x-badge>{{ $n['detector'] }}</x-badge></td>
                            <td><span class="t-sm t-3">{{ $n['note'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- ---------------------------------------------------------- data -- --}}

    <x-card title="This dataset">
        <div class="stack-4">
            <div class="between" style="align-items:flex-start;gap:var(--s-5)">
                <div style="max-width:58ch">
                    <div class="semi">Detection</div>
                    <div class="t-sm t-3" style="margin-top:3px;line-height:1.55">
                        All 14 detectors last ran
                        {{ $lastRun ? Fmt::ago($lastRun) : 'never' }}. They also run automatically on a
                        schedule, and a targeted subset re-runs the moment you apply an action — which
                        is what lets a finding retire itself.
                    </div>
                </div>
                <div class="row-2" style="flex:none">
                    <button class="btn btn--ghost" wire:click="rerun"
                            wire:loading.attr="disabled" wire:target="rerun">
                        <x-icon name="reset" size="15" />
                        <span wire:loading.remove wire:target="rerun">Re-run detectors</span>
                        <span wire:loading wire:target="rerun">Running…</span>
                    </button>
                </div>
            </div>

            <div class="between" style="align-items:flex-start;gap:var(--s-5)">
                <div style="max-width:58ch">
                    <div class="semi">Records</div>
                    <div class="t-sm t-3" style="margin-top:3px;line-height:1.55">
                        {{ Fmt::num($counts['leads']) }} leads, {{ Fmt::num($counts['properties']) }} properties
                        and {{ Fmt::num($counts['events']) }} business events, all scoped to
                        {{ app('nexabi.tenant')->name }}. Every query in the product carries that scope
                        at the database layer, not as a filter someone can forget to write.
                    </div>
                </div>
                <div class="row-2" style="flex:none">
                    <a class="btn btn--ghost" href="{{ route('app.activity') }}" wire:navigate>
                        <span>Open the action log</span>
                    </a>
                </div>
            </div>
        </div>
    </x-card>
</div>
