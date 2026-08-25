<?php

namespace App\Livewire\Pages;

use App\Engine\Stats;
use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Deal;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Analytics — cross-module views that belong to no single business area.
 *
 * Deliberately no dual-axis charts anywhere in this file. Two measures of
 * different scale get two charts, never two y-scales: a second axis lets the
 * author choose the story by choosing the scaling.
 */
#[Layout('layouts.app')]
#[Title('Analytics')]
class Analytics extends Component
{
    use InteractsWithEngine;

    #[Url(except: 'growth')]
    public string $lens = 'growth';

    public function setLens(string $lens): void
    {
        $this->lens = $lens;
    }

    public function render()
    {
        $m = $this->metrics();
        $sales = $m->sales(90);
        $leads = $m->leads(90);
        $properties = $m->properties();
        $marketing = $m->marketing();
        $customers = $m->customers();

        return view('livewire.pages.analytics', array_merge([
            'sales' => $sales,
            'leads' => $leads,
            'properties' => $properties,
            'marketing' => $marketing,
            'customers' => $customers,
        ], match ($this->lens) {
            'efficiency' => $this->efficiency($sales, $leads, $properties, $marketing, $customers),
            'mix' => $this->mix($leads, $properties, $customers),
            default => $this->growth($sales, $leads, $customers),
        }));
    }

    /* ------------------------------------------------------------ growth */

    private function growth(array $sales, array $leads, array $customers): array
    {
        return [
            'revenueTrend' => Stats::trendPct(array_column($sales['series'], 'value')),
            'leadTrend' => Stats::trendPct(array_column($leads['series'], 'value')),
            'charts' => [
                'revenue' => [
                    'series' => [['name' => 'Revenue', 'data' => $sales['series']]],
                    'opts' => ['format' => 'money', 'height' => 250],
                ],
                'leadVolume' => [
                    'series' => [[
                        'name' => 'Leads',
                        'color' => 'var(--c3)',
                        'data' => $leads['series'],
                    ]],
                    'opts' => ['format' => 'count', 'height' => 200],
                ],
                'deals' => [
                    'data' => collect($sales['countSeries'])->map(fn ($m) => [
                        'label' => $m['label'],
                        'value' => $m['value'],
                        'partial' => $m['partial'],
                    ])->all(),
                    'opts' => ['format' => 'count', 'height' => 200, 'color' => 'var(--c2)'],
                ],
            ],
        ];
    }

    /* -------------------------------------------------------- efficiency */

    private function efficiency(array $sales, array $leads, array $properties, array $marketing, array $customers): array
    {
        return [
            // Three is the number people quote. Below it, acquisition costs
            // more than the relationship is worth over its whole life.
            'ltvCac' => $marketing['cac'] ? $customers['avgLtv'] / $marketing['cac'] : 0.0,
            'charts' => [
                'conversion' => [
                    'data' => collect($leads['bySource'])->sortByDesc('conversion')->map(fn ($x) => [
                        'label' => $x['key'],
                        'value' => $x['conversion'],
                        'note' => Fmt::num($x['leads']).' leads',
                    ])->values()->all(),
                    'opts' => ['format' => 'pct'],
                ],
                'cpl' => [
                    'data' => collect($marketing['byChannel'])->sortBy('cpl')->map(fn ($x) => [
                        'label' => $x['key'],
                        'value' => $x['cpl'],
                        'note' => Fmt::moneyShort($x['spend']).' spend',
                    ])->values()->all(),
                    'opts' => ['format' => 'money', 'color' => 'var(--c4)'],
                ],
                'domByType' => [
                    'data' => collect($properties['byType'])
                        ->filter(fn ($x) => $x['sold'] > 2)
                        ->map(fn ($x) => [
                            'label' => $x['key'],
                            'value' => round($x['avgDom']),
                            'note' => Fmt::num($x['sold']).' sold',
                        ])->values()->all(),
                    'opts' => ['format' => 'count', 'height' => 210, 'color' => 'var(--c3)'],
                ],
            ],
        ];
    }

    /* --------------------------------------------------------------- mix */

    private function mix(array $leads, array $properties, array $customers): array
    {
        $byLocation = Deal::query()->where('won', true)
            ->selectRaw('location, SUM(value) v')
            ->groupBy('location')->orderByDesc('v')->limit(6)->get();

        $locs = collect($properties['byLocation'])->take(9);

        return [
            'charts' => [
                'revenueMix' => [
                    'data' => $byLocation->map(fn ($r) => [
                        'label' => $r->location, 'value' => (float) $r->v,
                    ])->all(),
                    'opts' => ['format' => 'money', 'centerLabel' => 'Revenue', 'size' => 200],
                ],
                'leadMix' => [
                    'data' => collect($leads['bySource'])->take(6)->map(fn ($x) => [
                        'label' => $x['key'], 'value' => $x['leads'],
                    ])->values()->all(),
                    'opts' => ['format' => 'count', 'centerLabel' => 'Leads', 'size' => 200],
                ],
                'inventory' => [
                    'categories' => $locs->pluck('key')->all(),
                    'series' => [
                        ['name' => 'Available', 'data' => $locs->pluck('available')->all()],
                        ['name' => 'Sold', 'data' => $locs->pluck('sold')->all()],
                    ],
                    'opts' => ['format' => 'count', 'height' => 250],
                ],
            ],
        ];
    }
}
