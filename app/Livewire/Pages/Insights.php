<?php

namespace App\Livewire\Pages;

use App\Engine\InsightEngine;
use App\Livewire\Concerns\InteractsWithEngine;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * AI Insights — the full ranked feed.
 *
 * Ordering is the product here. Findings are sorted by
 * severity × confidence × role weight × log(impact), and the per-area cap that
 * the dashboard applies is lifted, because the user came here specifically to
 * see everything.
 */
#[Layout('layouts.app')]
#[Title('AI Insights')]
class Insights extends Component
{
    use InteractsWithEngine;

    #[Url(except: 'all')]
    public string $area = 'all';

    #[Url(except: 'all')]
    public string $band = 'all';

    /** Set when arriving from a "see the evidence" link. */
    #[Url(except: '')]
    public string $id = '';

    public function setArea(string $area): void
    {
        $this->area = $this->area === $area ? 'all' : $area;
    }

    public function setBand(string $band): void
    {
        $this->band = $band;
    }

    public function clearFilters(): void
    {
        $this->area = 'all';
        $this->band = 'all';
    }

    public function rerun(): void
    {
        $found = $this->engine()->run();
        $this->metrics()->flush();
        $this->dispatch('engine-changed');
        $this->toast(
            'Detectors re-run',
            "All 14 detectors checked the current data · {$found} live findings",
            'ai',
        );
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $engine = $this->engine();
        $all = $engine->live();
        $counts = $engine->counts();

        $shown = collect($all)
            ->when($this->area !== 'all', fn ($c) => $c->where('area', $this->area))
            ->when($this->band !== 'all', fn ($c) => $c->where('band', $this->band))
            ->values();

        return view('livewire.pages.insights', [
            'all' => $all,
            'shown' => $shown,
            'counts' => $counts,
            'impact' => $engine->impactOnTable($all),
            'limits' => $this->limits(),
            'roleLabel' => InsightEngine::ROLES[$this->role()]['label'],
        ]);
    }

    /**
     * Operational limits.
     *
     * Absorbed from what used to be a separate Alerts page. These are hard
     * tripwires rather than findings — lines the business said it would not
     * cross — so they belong beneath the feed rather than in a nav slot of
     * their own.
     */
    private function limits(): array
    {
        $m = $this->metrics();
        $leads = $m->leads();
        $finance = $m->finance();
        $properties = $m->properties();

        return [
            [
                'label' => 'Leads past the 14-day contact threshold',
                'value' => Fmt::num($leads['stale']).' leads',
                'limit' => Fmt::num($leads['staleBaseline']),
                'breached' => $leads['stale'] > $leads['staleBaseline'] * 1.5,
                'route' => route('app.leads', ['filter' => 'stale']),
            ],
            [
                'label' => 'Balance more than 90 days overdue',
                'value' => Fmt::moneyShort($finance['aging']['90+'] ?? 0),
                'limit' => Fmt::money(0),
                'breached' => ($finance['aging']['90+'] ?? 0) > 0,
                'route' => route('app.finance', ['filter' => 'overdue']),
            ],
            [
                'label' => 'Collection ratio below target',
                'value' => Fmt::pct($finance['collectionRatio'], 0),
                'limit' => '92%',
                'breached' => $finance['collectionRatio'] < 0.92,
                'route' => route('app.finance'),
            ],
            [
                'label' => 'Listings past twice the median time on market',
                'value' => Fmt::num($properties['stale']).' listings',
                'limit' => Fmt::num(round($properties['available'] * 0.06)),
                'breached' => $properties['stale'] > $properties['available'] * 0.09,
                'route' => route('app.properties', ['filter' => 'stale']),
            ],
        ];
    }
}
