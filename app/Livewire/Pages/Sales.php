<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Deal;
use App\Models\Lead;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Sales — revenue, funnel and agent performance.
 *
 * Three sub-views under one route so the agent-divergence and funnel-erosion
 * findings have somewhere specific to land (?view=agents, ?view=funnel).
 */
#[Layout('layouts.app')]
#[Title('Sales')]
class Sales extends Component
{
    use InteractsWithEngine, WithPagination;

    #[Url(except: 'overview')] public string $view = 'overview';
    #[Url(except: '')] public string $q = '';

    public function setView(string $view): void
    {
        $this->view = $view;
        $this->resetPage();
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $m = $this->metrics();
        $sales = $m->sales(90);
        $agents = $m->agents();
        $funnel = $m->funnel();

        return view('livewire.pages.sales', array_merge([
            'sales' => $sales,
            'agents' => $agents,
            'funnel' => $funnel,
        ], match ($this->view) {
            'agents' => $this->agentsView($agents),
            'funnel' => $this->funnelView($funnel),
            default => $this->overview($sales),
        }));
    }

    /* ---------------------------------------------------------- overview */

    private function overview(array $sales): array
    {
        $won = fn () => Deal::query()->where('won', true);

        $byLocation = $won()
            ->selectRaw('location, SUM(value) v, COUNT(*) c')
            ->groupBy('location')->orderByDesc('v')->limit(8)->get();

        $byType = $won()
            ->selectRaw('property_type, SUM(value) v, COUNT(*) c')
            ->groupBy('property_type')->orderByDesc('v')->get();

        return [
            'charts' => [
                'revenue' => [
                    'series' => [['name' => 'Revenue', 'data' => $sales['series']]],
                    'opts' => ['format' => 'money', 'height' => 240],
                ],
                'byLocation' => [
                    'data' => $byLocation->map(fn ($r) => [
                        'label' => $r->location,
                        'value' => (float) $r->v,
                        'note' => $r->c.' deals',
                    ])->all(),
                    'opts' => ['format' => 'money'],
                ],
                'byType' => [
                    'data' => $byType->map(fn ($r) => [
                        'label' => $r->property_type,
                        'value' => (float) $r->v,
                        'note' => $r->c.' deals',
                    ])->all(),
                    'opts' => ['format' => 'money', 'color' => 'var(--c3)'],
                ],
            ],
            'deals' => Deal::query()
                ->with(['lead:id,name', 'property:id,title', 'agent:id,name'])
                ->where('won', true)
                ->when($this->q, fn ($qq) => $qq->whereHas('lead', fn ($l) => $l->where('name', 'like', '%'.$this->q.'%')))
                ->orderByDesc('closed_at')
                ->paginate(25),
        ];
    }

    /* ------------------------------------------------------------ funnel */

    private function funnelView(array $funnel): array
    {
        // Only weeks with a real cohort behind them. A 100% step conversion
        // computed from two leads is noise wearing a number's clothes.
        $weekly = collect($funnel['weeklyStepConversion'])->filter(fn ($w) => $w['n'] >= 3)->values();

        $reasons = Lead::query()
            ->where('status', 'lost')->whereNotNull('lost_reason')
            ->selectRaw('lost_reason, COUNT(*) c')
            ->groupBy('lost_reason')->orderByDesc('c')->get();

        return [
            'charts' => [
                'funnel' => ['stages' => $funnel['stages']],
                'weekly' => $weekly->count() > 3 ? [
                    'series' => [[
                        'name' => 'Step conversion',
                        'color' => 'var(--c2)',
                        'data' => $weekly->map(fn ($w) => [
                            'label' => $this->weekLabel($w['week']),
                            'value' => $w['value'],
                        ])->all(),
                    ]],
                    'opts' => ['format' => 'pct', 'height' => 220, 'showDots' => true],
                ] : null,
                'reasons' => [
                    'data' => $reasons->map(fn ($r) => [
                        'label' => $r->lost_reason,
                        'value' => (int) $r->c,
                    ])->all(),
                    'opts' => ['format' => 'count', 'color' => 'var(--c4)'],
                ],
            ],
            'lostCount' => (int) $reasons->sum('c'),
        ];
    }

    /* ------------------------------------------------------------ agents */

    private function agentsView(array $agents): array
    {
        $base = $agents['baselineCloseRate'] ?: 0.0001;

        return [
            'charts' => [
                'closeRate' => [
                    'data' => collect($agents['rows'])
                        ->filter(fn ($x) => $x['leads'] >= 15)
                        ->sortByDesc('closeRate')
                        ->map(fn ($x) => [
                            'label' => $x['name'],
                            'value' => $x['closeRate'],
                            'note' => Fmt::num($x['won']).' won of '.Fmt::num($x['leads'])
                                .' leads · '.Fmt::moneyShort($x['revenue']),
                            // Orange for the laggards, green for the stars. The
                            // detector reads the same two thresholds.
                            'color' => $x['closeRate'] < $base * 0.62 ? 'var(--c2)'
                                : ($x['closeRate'] > $base * 1.3 ? 'var(--c6)' : 'var(--c1)'),
                        ])->values()->all(),
                    'opts' => ['format' => 'pct'],
                ],
            ],
            'baseline' => $agents['baselineCloseRate'],
        ];
    }

    /** YEARWEEK(mode 3) -> "12 May". */
    private function weekLabel(int|string $yearWeek): string
    {
        $s = (string) $yearWeek;
        $year = (int) substr($s, 0, 4);
        $week = (int) substr($s, 4);

        return now()->setISODate($year, $week)->format('j M');
    }
}
