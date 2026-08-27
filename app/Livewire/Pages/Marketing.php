<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Support\Fmt;
use App\Support\Nigeria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Marketing — a record of what was run, and what it actually produced.
 *
 * Two halves that answer different questions.
 *
 * PAID SPEND is measured on commission, because that is what the agency banks.
 * Dividing gross property value by ad spend flatters a paid operation that is
 * in fact losing money on every customer it acquires.
 *
 * SELF-REPORTED CHANNEL is the other half, and it is the one that used to be
 * missing. "Where did you hear about us" is asked at both the questionnaire and
 * the registration, so word of mouth, a radio advert and a church announcement
 * — none of which carry a click — are measured on the investors they actually
 * produced rather than being invisible next to whatever had a tracking link.
 */
#[Layout('layouts.app')]
#[Title('Marketing')]
class Marketing extends Component
{
    use InteractsWithEngine;

    /* ------------------------------------------------- the record form */

    public bool $adding = false;

    public ?int $editing = null;

    /** The row whose outcome is being keyed in, if any. */
    public ?int $counting = null;

    public string $name = '';
    public string $channel = 'Offline';
    public string $source = '';
    public string $location = '';
    public string $spend = '';
    public string $impressions = '';
    public string $startAt = '';
    public string $endAt = '';
    public string $expectedLeads = '';
    public string $expectedRevenue = '';
    public string $notes = '';

    public string $actualLeads = '';
    public string $actualRevenue = '';

    public function addRecord(): void
    {
        $this->resetForm();
        $this->adding = true;
        $this->startAt = now()->toDateString();
    }

    public function edit(int $id): void
    {
        $c = Campaign::findOrFail($id);

        $this->editing = $c->id;
        $this->name = (string) $c->name;
        $this->channel = (string) $c->channel;
        $this->source = (string) $c->source;
        $this->location = (string) $c->location;
        $this->spend = (string) $c->spend;
        $this->impressions = (string) $c->impressions;
        $this->startAt = $c->start_at?->toDateString() ?? '';
        $this->endAt = $c->end_at?->toDateString() ?? '';
        $this->expectedLeads = (string) ($c->expected_leads ?? '');
        $this->expectedRevenue = (string) ($c->expected_revenue ?? '');
        $this->notes = (string) $c->notes;
        $this->adding = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'adding', 'editing', 'counting', 'name', 'source', 'location', 'spend',
            'impressions', 'startAt', 'endAt', 'expectedLeads', 'expectedRevenue',
            'notes', 'actualLeads', 'actualRevenue',
        ]);
        $this->channel = 'Offline';
        $this->resetErrorBag();
    }

    /**
     * Record a piece of marketing.
     *
     * The expectation is required and the outcome is not, deliberately. You
     * know what a billboard was sold to you as on the day you book it; you do
     * not know what it produced for another two months. Demanding both up
     * front is how a record like this ends up never being entered at all.
     */
    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:140'],
            'channel' => ['required', 'string', 'in:'.implode(',', array_keys(Campaign::CHANNELS))],
            'source' => ['required', 'string', 'max:80'],
            'location' => ['nullable', 'string', 'max:80'],
            'spend' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'impressions' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'startAt' => ['required', 'date'],
            'endAt' => ['nullable', 'date', 'after_or_equal:startAt'],
            'expectedLeads' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'expectedRevenue' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'startAt' => 'start date',
            'endAt' => 'end date',
            'expectedLeads' => 'expected leads',
            'expectedRevenue' => 'expected revenue',
        ]);

        Campaign::updateOrCreate(
            ['id' => $this->editing],
            [
                'tenant_id' => $this->tenant()->id,
                'name' => $data['name'],
                'channel' => $data['channel'],
                'source' => $data['source'],
                'location' => $data['location'] ?: null,
                'spend' => (float) $data['spend'],
                'impressions' => (int) ($data['impressions'] ?: 0),
                'clicks' => 0,
                'start_at' => $data['startAt'],
                'end_at' => $data['endAt'] ?: null,
                'expected_leads' => $data['expectedLeads'] !== '' ? (int) $data['expectedLeads'] : null,
                'expected_revenue' => $data['expectedRevenue'] !== '' ? (float) $data['expectedRevenue'] : null,
                'notes' => $data['notes'] ?: null,
                'status' => 'active',
                'manual' => true,
                'owner_user_id' => auth()->id(),
            ],
        );

        $wasEditing = $this->editing !== null;
        $this->resetForm();

        // The engine reads campaigns; a new record changes what campaignBurn
        // and sourceQuality see, so the cached figures have to go.
        $this->metrics()->flush();
        $this->dispatch('engine-changed');

        $this->toast(
            $wasEditing ? 'Record updated' : 'Marketing recorded',
            'NexABI can measure it now that the spend is on the books.',
            'pos',
        );
    }

    /* ----------------------------------------------------- the outcome */

    public function count(int $id): void
    {
        $c = Campaign::findOrFail($id);

        $this->counting = $c->id;
        $this->actualLeads = (string) ($c->actual_leads ?? '');
        $this->actualRevenue = (string) ($c->actual_revenue ?? '');
    }

    public function cancelCount(): void
    {
        $this->reset(['counting', 'actualLeads', 'actualRevenue']);
        $this->resetErrorBag();
    }

    /**
     * Key in what it actually produced.
     *
     * Zero is a legitimate answer and is stored as zero — that is a finding.
     * Null means nobody has counted yet, which is a chore, and the two must
     * stay distinguishable or an uncounted billboard reads as a failed one.
     */
    public function saveCount(): void
    {
        $data = $this->validate([
            'actualLeads' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'actualRevenue' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
        ], attributes: [
            'actualLeads' => 'leads produced',
            'actualRevenue' => 'revenue produced',
        ]);

        $c = Campaign::findOrFail($this->counting);
        $c->forceFill([
            'actual_leads' => (int) $data['actualLeads'],
            'actual_revenue' => $data['actualRevenue'] !== '' ? (float) $data['actualRevenue'] : null,
        ])->save();

        $delivery = $c->deliveryRate();

        $this->cancelCount();
        $this->metrics()->flush();
        $this->dispatch('engine-changed');

        $this->toast(
            'Outcome recorded',
            $delivery === null
                ? $c->name.' is now measurable.'
                : $c->name.' delivered '.Fmt::pct($delivery, 0).' of what was expected.',
            $delivery !== null && $delivery < 0.6 ? 'neg' : 'pos',
        );
    }

    public function pause(int $campaignId, string $name): void
    {
        $result = $this->bus()->manual(
            type: 'PAUSE_CAMPAIGN',
            payload: ['id' => $campaignId],
            label: "Pause \"{$name}\"",
            key: 'campaign:pause:'.$campaignId,
            userId: auth()->id(),
            area: 'marketing',
        );

        $this->toast(
            $result['ok'] ? 'Campaign paused' : 'Not applied',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        if ($result['ok']) {
            $this->metrics()->flush();
            $this->dispatch('engine-changed');
        }
    }

    /**
     * What each self-reported channel actually produced.
     *
     * Three counts joined in PHP rather than one query with three joins: a
     * single statement fanning out across leads and commitments double-counts
     * revenue, which is the classic way to make an attribution table lie.
     */
    private function bySource(): array
    {
        $leads = Lead::query()->whereNotNull('heard_about_us')
            ->selectRaw('heard_about_us, COUNT(*) c')
            ->groupBy('heard_about_us')->pluck('c', 'heard_about_us');

        $investors = Customer::query()->whereNotNull('heard_about_us')
            ->selectRaw('heard_about_us, COUNT(*) c')
            ->groupBy('heard_about_us')->pluck('c', 'heard_about_us');

        $sales = Deal::query()
            ->join('customers', 'customers.id', '=', 'deals.customer_id')
            ->whereIn('deals.status', array_merge(Deal::LIVE, ['completed']))
            ->whereNotNull('customers.heard_about_us')
            ->selectRaw('customers.heard_about_us h, COUNT(*) c, SUM(deals.value) v')
            ->groupBy('h')->get()->keyBy('h');

        $rows = [];

        foreach (Nigeria::REFERRAL_SOURCES as $key => $label) {
            $leadCount = (int) ($leads[$key] ?? 0);
            $investorCount = (int) ($investors[$key] ?? 0);
            $soldCount = (int) ($sales[$key]->c ?? 0);
            $soldValue = (float) ($sales[$key]->v ?? 0);

            if ($leadCount + $investorCount === 0) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'label' => $label,
                'leads' => $leadCount,
                'investors' => $investorCount,
                'sales' => $soldCount,
                'value' => $soldValue,
                // Of the people who registered from this channel, how many
                // went on to buy. Leads are excluded from the denominator
                // because most of them never became anybody's customer.
                'conversion' => $investorCount > 0 ? $soldCount / $investorCount : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $rows;
    }

    /**
     * Every piece of marketing on the books, expectation beside outcome.
     *
     * Hand-entered records first. They are the ones that need a human to come
     * back and close the loop — a tracked campaign counts itself, a billboard
     * sits at "not counted yet" until somebody says otherwise, and burying
     * those under ninety auto-attributed rows is how they stay uncounted.
     *
     * Lead and revenue counts are loaded as aggregates rather than per row:
     * this list runs to a hundred campaigns and each one would otherwise cost
     * two queries to draw a single number.
     */
    private function records(): \Illuminate\Support\Collection
    {
        return Campaign::query()
            ->withCount('leads')
            ->withSum(['deals as won_value' => fn ($q) => $q->where('won', true)], 'value')
            ->orderByDesc('manual')
            ->orderByDesc('start_at')
            ->limit(60)
            ->get();
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $mk = $this->metrics()->marketing();

        return view('livewire.pages.marketing', [
            'mk' => $mk,
            'bySource' => $this->bySource(),
            'records' => $this->records(),
            'channels' => Campaign::CHANNELS,
            'charts' => [
                'spend' => [
                    'data' => collect($mk['byChannel'])
                        ->map(fn ($c) => ['label' => $c['key'], 'value' => $c['spend']])
                        ->values()->all(),
                    'opts' => ['format' => 'money', 'centerLabel' => 'Total spend', 'size' => 200],
                ],
                'roas' => [
                    'data' => collect($mk['byChannel'])->sortByDesc('roas')->map(fn ($c) => [
                        'label' => $c['key'],
                        'value' => round($c['roas'], 2),
                        'note' => Fmt::moneyShort($c['spend']).' spend · '
                            .Fmt::num($c['leads']).' leads · '
                            .Fmt::pct($c['conversion'], 1).' conversion',
                        // Below 1× the campaign costs more than it returns.
                        // That is the only threshold on this page that matters.
                        'color' => $c['roas'] < 1 ? 'var(--c2)' : 'var(--c1)',
                    ])->values()->all(),
                    'opts' => ['format' => 'number'],
                ],
            ],
        ]);
    }
}
