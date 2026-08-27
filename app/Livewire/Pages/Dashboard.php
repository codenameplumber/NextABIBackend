<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Recommendation;
use App\Models\User;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard — the executive answer to "what is going on and what do I do".
 *
 * Reading order is the design. Someone opening this at 8am gets, in order:
 *
 *   1. What the AI makes of the business right now  (briefing)
 *   2. The single thing most worth doing today      (decision bar)
 *   3. The money                                    (KPIs, with definitions)
 *   4. Where the problems are across seven areas    (health strip)
 *   5. The evidence                                 (charts + full feed)
 *
 * A BI product that leads with charts is asking the user to do the analysis
 * themselves. The chart is the evidence, not the headline — so it goes last.
 */
#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use InteractsWithEngine;

    #[On('engine-changed')]
    public function refresh(): void
    {
        // Metrics memoises per instance and caches per tenant; an action that
        // changed rows must not be read through yesterday's aggregates.
        $this->metrics()->flush();
    }

    public function render()
    {
        $m = $this->metrics();

        $headline = $m->headline();
        $sales = $m->sales(90);
        $leads = $m->leads(90);
        $properties = $m->properties();
        $funnel = $m->funnel();

        $feed = $this->engine()->feed($this->role(), 8);
        $counts = $this->engine()->counts();

        // Worst live finding per area, for the health strip.
        $worstByArea = collect($this->engine()->live())
            ->groupBy('area')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'worst' => $group->sortByDesc('priority')->first(),
            ]);

        return view('livewire.pages.dashboard', [
            'charts' => $this->charts($sales, $leads, $properties, $funnel),
            'headline' => $headline,
            'sales' => $sales,
            'leads' => $leads,
            'properties' => $properties,
            'funnel' => $funnel,
            'feed' => $feed,
            'counts' => $counts,
            'worstByArea' => $worstByArea,
            'impact' => $this->engine()->impactOnTable($feed),
            'brief' => $this->narrator()->brief($headline, $feed),
            'log' => ActionLog::with('insight')->latest()->limit(4)->get(),

            // The three figures this platform is asked for before anything
            // else: how many people are on it, what it has taken, and who is
            // selling. The engine below answers "why"; these answer "how big".
            'platform' => $this->platform(),
        ]);
    }

    /**
     * Platform scale.
     *
     * Counted rather than derived from the metrics layer: these are questions
     * about the platform itself, not about the business it is analysing, and
     * pushing them through a cached aggregate would mean a realtor approved a
     * minute ago does not appear for ten.
     */
    private function platform(): array
    {
        $sold = Deal::whereIn('status', array_merge(Deal::LIVE, ['completed']));

        return [
            'users' => (int) User::count(),
            'investors' => (int) Customer::count(),
            'unassigned' => (int) Customer::query()->unassigned()->count(),
            'realtors' => (int) Agent::query()->approved()->count(),
            'realtorsPending' => (int) Agent::query()->pending()->count(),
            'commitments' => (int) (clone $sold)->count(),
            'commitmentsPending' => (int) Deal::where('status', 'pending')->count(),
            'soldValue' => (float) (clone $sold)->sum('value'),
            'commissionOwed' => (float) CommissionPayout::where('status', 'owed')->sum('amount'),
        ];
    }

    /**
     * Chart payloads.
     *
     * Built here rather than in the template because a chart is data, not
     * markup — and because a `note` on a bar has to be a formatted string
     * before it crosses to the client, where there is no ₦ formatter.
     */
    private function charts(array $sales, array $leads, array $properties, array $funnel): array
    {
        return [
            'revenue' => [
                'series' => [['name' => 'Revenue', 'data' => $sales['series']]],
                'opts' => ['format' => 'money', 'height' => 230],
            ],

            'funnel' => ['stages' => $funnel['stages']],

            'bySource' => [
                'data' => collect($leads['bySource'])->take(7)->map(fn ($x) => [
                    'label' => $x['key'],
                    'value' => $x['pipeline'],
                    'note' => Fmt::num($x['leads']).' leads · '
                        .Fmt::pct($x['conversion'], 1).' eventually close',
                ])->values()->all(),
                'opts' => ['format' => 'money'],
            ],

            'byLocation' => [
                'data' => collect($properties['byLocation'])->take(9)->map(fn ($x) => [
                    'label' => $x['key'],
                    'value' => $x['available'],
                    'note' => Fmt::num($x['inquiries']).' inquiries · '
                        .Fmt::pct($x['sellThrough'], 0).' sell-through',
                ])->values()->all(),
                'opts' => ['format' => 'count', 'height' => 210, 'color' => 'var(--c3)'],
            ],
        ];
    }

    /**
     * Apply the recommendation on the decision bar.
     *
     * Deliberately a full round trip through the action bus rather than a
     * shortcut: same transaction, same idempotency guard, same targeted
     * re-detection as applying it from the card itself.
     */
    public function applyTop(int $recommendationId): void
    {
        $rec = Recommendation::find($recommendationId);

        if (! $rec) {
            $this->toast('That recommendation is no longer available.', kind: 'neg');

            return;
        }

        $result = $this->bus()->execute($rec, auth()->id());

        $this->toast(
            $result['ok'] ? $rec->label : 'Not applied',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        if ($result['ok']) {
            $this->metrics()->flush();
            $this->dispatch('engine-changed');
        }
    }
}
