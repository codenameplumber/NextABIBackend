<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Reports — generated narrative summaries, not exported spreadsheets.
 *
 * The value here is that the summary is written from the same computed facts
 * the insight cards use, so a report can never disagree with the dashboard.
 * That is not a convention anyone has to maintain: both read the same metrics
 * instance, so there is no second number to drift.
 */
#[Layout('layouts.app')]
#[Title('Reports')]
class Reports extends Component
{
    use InteractsWithEngine;

    public const REPORTS = [
        'exec' => ['label' => 'Executive summary', 'title' => 'Executive summary', 'period' => 'the last 90 days'],
        'sales' => ['label' => 'Sales performance', 'title' => 'Sales performance', 'period' => 'the last 90 days'],
        'pipeline' => ['label' => 'Pipeline health', 'title' => 'Pipeline health', 'period' => 'current'],
        'finance' => ['label' => 'Receivables', 'title' => 'Receivables review', 'period' => 'current'],
        'marketing' => ['label' => 'Marketing return', 'title' => 'Marketing return', 'period' => 'campaign lifetime'],
    ];

    #[Url(except: 'exec')]
    public string $report = 'exec';

    public function setReport(string $key): void
    {
        if (isset(self::REPORTS[$key])) {
            $this->report = $key;
        }
    }

    public function render()
    {
        $m = $this->metrics();
        $spec = self::REPORTS[$this->report] ?? self::REPORTS['exec'];

        $insights = array_slice($this->engine()->live(), 0, 6);

        return view('livewire.pages.reports', array_merge([
            'spec' => $spec,
            'insights' => $insights,
            'summary' => $this->narrator()->summarise(
                $spec['title'],
                $spec['period'],
                $this->factsFor($this->report, $m),
                $insights,
            ),
        ], $this->body($this->report, $m)));
    }

    /**
     * The facts the narrator is allowed to see for this report.
     *
     * Scoped per report rather than handing over everything: a receivables
     * review that mentions campaign ROAS is a report nobody asked for.
     */
    private function factsFor(string $key, $m): array
    {
        return match ($key) {
            'sales' => array_intersect_key($m->sales(90), array_flip(
                ['revenue', 'revenueChange', 'deals', 'dealsChange', 'avgDeal', 'winRate', 'winRateChange', 'cycleDays'],
            )),
            'pipeline' => array_intersect_key($m->leads(90), array_flip(
                ['pipeline', 'open', 'new', 'newChange', 'stale', 'stalePipeline', 'hot', 'conversion', 'avgScore'],
            )),
            'finance' => array_intersect_key($m->finance(), array_flip(
                ['collected', 'outstanding', 'overdueAmount', 'overdueCount', 'collectionRatio', 'expected30', 'atRiskAccounts'],
            )),
            'marketing' => array_intersect_key($m->marketing(), array_flip(
                ['spend', 'leads', 'won', 'revenue', 'cpl', 'cac', 'roas', 'conversion'],
            )),
            default => $m->headline(),
        };
    }

    private function body(string $key, $m): array
    {
        return match ($key) {
            'sales' => $this->salesBody($m),
            'pipeline' => $this->pipelineBody($m),
            'finance' => $this->financeBody($m),
            'marketing' => $this->marketingBody($m),
            default => $this->execBody($m),
        };
    }

    private function execBody($m): array
    {
        $s = $m->sales(90);
        $l = $m->leads(90);
        $p = $m->properties();
        $f = $m->finance();

        return [
            'stats' => [
                'Revenue · 90d' => Fmt::moneyShort($s['revenue']),
                'Deals closed' => Fmt::num($s['deals']),
                'Win rate' => Fmt::pct($s['winRate'], 1),
                'Open pipeline' => Fmt::moneyShort($l['pipeline']),
                'Inventory value' => Fmt::moneyShort($p['inventoryValue']),
                'Overdue' => Fmt::moneyShort($f['overdueAmount']),
            ],
            'charts' => [
                'main' => [
                    'kind' => 'line',
                    'title' => 'Revenue trajectory',
                    'payload' => [
                        'series' => [['name' => 'Revenue', 'data' => $s['series']]],
                        'opts' => ['format' => 'money', 'height' => 230],
                    ],
                ],
            ],
        ];
    }

    private function salesBody($m): array
    {
        $s = $m->sales(90);
        $a = $m->agents();

        return [
            'stats' => [
                'Revenue · 90d' => Fmt::moneyShort($s['revenue']),
                'Deals closed' => Fmt::num($s['deals']),
                'Average deal' => Fmt::moneyShort($s['avgDeal']),
                'Win rate' => Fmt::pct($s['winRate'], 1),
                'Avg cycle' => round($s['cycleDays']).' days',
                'Top 3 share' => Fmt::pct($a['concentration'], 0),
            ],
            'charts' => [
                'main' => [
                    'kind' => 'line',
                    'title' => 'Revenue by month',
                    'payload' => [
                        'series' => [['name' => 'Revenue', 'data' => $s['series']]],
                        'opts' => ['format' => 'money', 'height' => 220],
                    ],
                ],
                'second' => [
                    'kind' => 'hbar',
                    'title' => 'Agent contribution',
                    'sub' => 'Top three account for '.Fmt::pct($a['concentration'], 0).' of revenue',
                    'payload' => [
                        'data' => collect($a['rows'])->take(10)->map(fn ($x) => [
                            'label' => $x['name'],
                            'value' => $x['revenue'],
                            'note' => Fmt::num($x['won']).' deals · '.Fmt::pct($x['closeRate'], 0).' close rate',
                        ])->all(),
                        'opts' => ['format' => 'money'],
                    ],
                ],
            ],
        ];
    }

    private function pipelineBody($m): array
    {
        $l = $m->leads(90);
        $fn = $m->funnel();

        return [
            'stats' => [
                'Open pipeline' => Fmt::moneyShort($l['pipeline']),
                'Open leads' => Fmt::num($l['open']),
                'New · 90d' => Fmt::num($l['new']),
                'Stale' => Fmt::num($l['stale']),
                'Scoring 70+' => Fmt::num($l['hot']),
                'Lead-to-sale' => Fmt::pct($l['conversion'], 1),
            ],
            'charts' => [
                'main' => [
                    'kind' => 'funnel',
                    'title' => 'Funnel',
                    'payload' => ['stages' => $fn['stages']],
                ],
                'second' => [
                    'kind' => 'hbar',
                    'title' => 'Pipeline by source',
                    'payload' => [
                        'data' => collect($l['bySource'])->take(8)->map(fn ($x) => [
                            'label' => $x['key'],
                            'value' => $x['pipeline'],
                            'note' => Fmt::num($x['leads']).' leads',
                        ])->values()->all(),
                        'opts' => ['format' => 'money'],
                    ],
                ],
            ],
        ];
    }

    private function financeBody($m): array
    {
        $f = $m->finance();

        return [
            'stats' => [
                'Collected' => Fmt::moneyShort($f['collected']),
                'Outstanding' => Fmt::moneyShort($f['outstanding']),
                'Overdue' => Fmt::moneyShort($f['overdueAmount']),
                'Collection ratio' => Fmt::pct($f['collectionRatio'], 0),
                'Due next 30d' => Fmt::moneyShort($f['expected30']),
                'Accounts at risk' => Fmt::num($f['atRiskAccounts']),
            ],
            'charts' => [
                'main' => [
                    'kind' => 'hbar',
                    'title' => 'Aging',
                    'payload' => [
                        'data' => collect($f['aging'])->map(fn ($v, $k) => [
                            'label' => $k.' days', 'value' => $v,
                        ])->values()->all(),
                        'opts' => ['format' => 'money', 'color' => 'var(--c4)'],
                    ],
                ],
            ],
        ];
    }

    private function marketingBody($m): array
    {
        $mk = $m->marketing();

        return [
            'stats' => [
                'Spend' => Fmt::moneyShort($mk['spend']),
                'Leads' => Fmt::num($mk['leads']),
                'Customers' => Fmt::num($mk['won']),
                'Cost per lead' => Fmt::moneyShort($mk['cpl']),
                'Acquisition cost' => Fmt::moneyShort($mk['cac']),
                'ROAS' => number_format($mk['roas'], 2).'×',
            ],
            'charts' => [
                'main' => [
                    'kind' => 'hbar',
                    'title' => 'Return by channel',
                    'payload' => [
                        'data' => collect($mk['byChannel'])->sortByDesc('roas')->map(fn ($c) => [
                            'label' => $c['key'],
                            'value' => round($c['roas'], 2),
                            'note' => Fmt::moneyShort($c['spend']).' spend',
                        ])->values()->all(),
                        'opts' => ['format' => 'number'],
                    ],
                ],
            ],
        ];
    }
}
