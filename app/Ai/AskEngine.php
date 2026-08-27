<?php

namespace App\Ai;

use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Engine\Stats;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\Fmt;
use App\Support\Vocab;

/**
 * Ask NexABI — a bounded-domain intent router over the metrics layer.
 *
 * Real-estate BI is a *bounded* domain. There are roughly a dozen things
 * anyone asks and a few dozen ways to slice them. A router that classifies
 * intent, extracts entities and dispatches to the metrics layer answers a
 * genuinely large share of real questions — and answers them with a card, a
 * chart and a drill-through, which is better than a paragraph.
 *
 * Where it cannot answer, it says so and offers what it can. Honest and useful
 * beats fake and fragile: a confidently wrong number is the one failure mode
 * that would actually discredit this product.
 *
 * The parse is shown back to the user under every answer, so when the router
 * misreads a question that is visible rather than hidden behind fluent prose.
 */
class AskEngine
{
    private NarratorContract $narrator;

    public function __construct(
        private Tenant $tenant,
        private Metrics $metrics,
        private InsightEngine $engine,
        ?NarratorContract $narrator = null,
    ) {
        // Optional so the three existing call sites keep working unchanged.
        // It is only ever reached after the router has already failed, so a
        // caller that does not pass one still gets every routed answer.
        $this->narrator = $narrator ?? app(NarratorContract::class);
    }

    /* ------------------------------------------------------------ grammar */

    private const WINDOWS = [
        ['re' => '/\btoday\b/i', 'days' => 1, 'label' => 'today'],
        ['re' => '/\b(this week|past week|last 7 days)\b/i', 'days' => 7, 'label' => 'the last 7 days'],
        ['re' => '/\b(last month|past month|last 30 days|past 30 days)\b/i', 'days' => 30, 'label' => 'the last 30 days'],
        ['re' => '/\b(last 60|past 60|two months)\b/i', 'days' => 60, 'label' => 'the last 60 days'],
        ['re' => '/\b(this quarter|last quarter|last 90 days|past 90 days|3 months|three months)\b/i', 'days' => 90, 'label' => 'the last 90 days'],
        ['re' => '/\b(last 6 months|six months|half year)\b/i', 'days' => 180, 'label' => 'the last 6 months'],
        ['re' => '/\b(this year|last year|12 months|ytd|year to date)\b/i', 'days' => 365, 'label' => 'the last 12 months'],
    ];

    /**
     * ORDER MATTERS — most specific first.
     *
     * A generic `leads` pattern placed early swallows "stale leads", and the
     * router then answers a different question than the one that was asked,
     * confidently. That is the exact failure this product cannot afford.
     */
    private const METRICS = [
        'stale' => '/\b(stale|cold|untouched|idle|no contact|neglected|forgotten)\b/i',
        'conversion' => '/\b(conversion|close rate|closing rate|win rate)\b/i',
        'pipeline' => '/\b(pipeline|potential|opportunit)/i',
        'overdue' => '/\b(overdue|arrears|outstanding|receivable|unpaid|debt|late)\b/i',
        'collections' => '/\b(collect|payments? received|cash|collection ratio)/i',

        /* The platform entities the instalment product added. All four sit
           ABOVE the generic patterns they would otherwise be swallowed by:
           "how many commitments are pending" contains "commitment", but it
           also contains nothing else — while "unassigned investors" would be
           eaten by `customers`, and "realtors" by `agents`, if either of those
           came first. */
        'commitments' => '/\b(commitments?|approval queue|awaiting approval|pending approval|to approve)\b/i',
        'unassigned' => '/\b(unassigned|no realtor|without a realtor|nobody assigned)\b/i',
        'instalments' => '/\b(instal+ments?|repayments?|payment plans?|payment schedules?)\b/i',
        'realtors' => '/\b(realtors?)\b/i',

        'satisfaction' => '/\b(satisf|happy|rating|reviews?|nps|sentiment)/i',
        'complaints' => '/\b(complain|feedback|unhappy|detractor)/i',
        'dom' => '/\b(days on market|time to sell|how long.*sell|dom)\b/i',
        'cpl' => '/\b(cpl|cost per lead)\b/i',
        'cac' => '/\b(cac|cost per (customer|acquisition)|acquisition cost)\b/i',
        'roas' => '/\b(roas|return on ad|marketing return)\b/i',
        'spend' => '/\b(spend|budget|marketing cost|ad spend)\b/i',
        'inventory' => '/\b(inventory|stock|listings?|propert|units?)\b/i',
        'revenue' => '/\b(revenue|sales|turnover|income|earnings)\b/i',
        'deals' => '/\b(deals?|closings?|sold|transactions?)\b/i',
        'agents' => '/\b(agents?|staff|team|reps?|performers?)\b/i',
        'customers' => '/\b(customers?|clients?|buyers?)\b/i',
        'leads' => '/\b(leads?|enquir|inquir|prospects?)\b/i',
    ];

    private const DIMENSIONS = [
        'location' => '/\bby (location|area|estate|neighbourhood|neighborhood)\b/i',
        'source' => '/\bby (source|channel)\b/i',
        'agent' => '/\bby (agent|rep|team member)\b/i',
        'type' => '/\bby (type|property type|category)\b/i',
        'segment' => '/\bby (segment|tier)\b/i',
        'month' => '/\bby (month|monthly|over time)\b/i',
    ];

    /**
     * ORDER MATTERS HERE TOO, and `causal` has to beat `recommend`.
     *
     * Almost every question worth asking of this product has the shape "why is
     * X happening and what should I do about it" — which matches both. With
     * `recommend` first, the router threw away the "why is X" half and
     * answered with a generic list of every outstanding recommendation in the
     * business, for a question that named one specific thing. Technically a
     * reply; useless as an answer.
     *
     * `recommend` still wins on a bare "what should I do next?", which is the
     * question it was actually written for.
     */
    private const INTENTS = [
        'help' => '/^(help|what can you|what do you|how do i use|capabilities)/i',
        'risk' => '/\b(risk|worry|worried|concern|problem|wrong|attention|urgent|alert)/i',
        'causal' => '/\b(why|what caused|reason|explain|driving|because)\b/i',
        'recommend' => '/\b(what should (i|we)|recommend|advise|next step|action|priorit)/i',
        'ranking' => '/\b(top|best|worst|highest|lowest|rank|leading|underperform|strongest|weakest|most|least)\b/i',
        'trend' => '/\b(trend|trending|over time|growth|growing|declining|falling|rising|month over month|compare)\b/i',
        'forecast' => '/\b(forecast|predict|projection|next month|next quarter|expect|will (we|i))\b/i',
        'count' => '/\b(how many|count|number of)\b/i',
        'value' => "/\b(how much|what is|what's|whats|total|show me|give me)\b/i",
    ];

    /**
     * What is being ranked, as distinct from what it is ranked BY.
     *
     * "Top 5 agents by revenue" has subject=agent and metric=revenue. Without
     * separating the two, the metric wins and the question gets answered about
     * the wrong thing.
     */
    private const SUBJECTS = [
        'agent' => '/\b(agents?|reps?|staff|team members?|performers?|salespe(rson|ople))\b/i',
        'campaign' => '/\b(campaigns?|ads?|adverts?)\b/i',
        'location' => '/\b(locations?|areas?|estates?|neighbourhoods?|neighborhoods?)\b/i',
        'source' => '/\b(sources?|channels?)\b/i',
        'property' => '/\b(propert(y|ies)|listings?|units?|inventory)\b/i',
        'customer' => '/\b(customers?|clients?|buyers?)\b/i',
    ];

    public const CAPABILITIES = [
        'Revenue, deals, pipeline and win rate over any period',
        'Lead volume, quality, staleness and conversion by source',
        'Property inventory, demand by segment and time on market',
        'Overdue payments, collection ratio and receivables aging',
        'Instalments recorded, still scheduled, and what is late',
        'Commitments awaiting approval, and which ones are blocked',
        'Realtors — approved, waiting, and who is producing',
        'Investors with no realtor attached, and how many',
        'Marketing spend, CPL, CAC and ROAS by campaign or channel',
        'Customer lifetime value, satisfaction and detractors',
        'Rankings — best or worst agents, sources, locations, campaigns',
        'Why a number moved, and what to do about it',
    ];

    /* --------------------------------------------------------------- ask */

    /**
     * @param  ?int  $fromInsight  a finding this question was asked *about*
     */
    public function ask(string $question, ?int $fromInsight = null): array
    {
        /**
         * A question arriving from a finding's "Find out more" is answered
         * about that finding, full stop.
         *
         * The router is very good at "how much is overdue" and hopeless at
         * "why is the Meta - Lagos Mainland Push campaign underperforming",
         * because nothing in that sentence is a metric it knows — so it
         * extracted no metric, matched no area, and replied with whatever the
         * highest-priority finding in the whole business happened to be. A
         * confident answer about an unrelated block of flats.
         *
         * Carrying the id removes the guesswork entirely. The user clicked a
         * specific card; there is no ambiguity to resolve.
         */
        if ($fromInsight && $found = $this->resolveFinding($fromInsight)) {
            return [...$found, 'parsed' => $this->classify($question), 'question' => $question];
        }

        $p = $this->classify($question);

        $res = match ($p['intent']) {
            'help' => $this->resolveHelp(),
            'risk' => $this->resolveRisk(),
            'recommend' => $this->resolveRecommend(),
            'ranking' => $this->resolveRanking($p),
            'forecast' => $this->resolveForecast(),
            'causal' => $this->resolveCausal($p),
            'trend' => $p['dim'] ? $this->resolveDimension($p) : $this->resolveTrend($p),
            default => $p['dim'] ? $this->resolveDimension($p) : $this->resolveValue($p),
        };

        // Second chances before admitting defeat.
        $res ??= $p['dim'] ? $this->resolveDimension($p) : null;
        $res ??= $p['metric'] ? $this->resolveValue([...$p, 'intent' => 'value']) : null;
        $res ??= $this->resolveUnknown($p);

        return [...$res, 'parsed' => $p, 'question' => $question];
    }

    private function classify(string $q): array
    {
        $text = mb_strtolower($q);

        $first = function (array $map) use ($text) {
            foreach ($map as $key => $re) {
                if (preg_match($re, $text)) {
                    return $key;
                }
            }

            return null;
        };

        $window = null;
        foreach (self::WINDOWS as $w) {
            if (preg_match($w['re'], $text)) {
                $window = $w;
                break;
            }
        }

        $dim = $first(self::DIMENSIONS);
        if (! $dim && preg_match('/\bper (agent|source|location)\b/i', $text, $m)) {
            $dim = $m[1];
        }

        // Free-text entity matching against known vocabulary.
        $find = fn (array $names) => collect($names)
            ->first(fn ($n) => str_contains($text, mb_strtolower($n)));

        $limit = 5;
        if (preg_match('/\btop (\d+)|\b(\d+)\s+(best|worst|top)/i', $text, $m)) {
            $limit = (int) ($m[1] ?: $m[2]);
        }

        return [
            'intent' => $first(self::INTENTS) ?? 'value',
            'metric' => $first(self::METRICS),
            'subject' => $first(self::SUBJECTS),
            'dim' => $dim,
            'days' => $window['days'] ?? 90,
            'windowLabel' => $window['label'] ?? 'the last 90 days',
            'location' => $find(array_column(Vocab::LOCATIONS, 'name')),
            'source' => $find(array_column(Vocab::SOURCES, 'name')),
            'type' => $find(array_column(Vocab::PROP_TYPES, 'name')),
            'limit' => max(1, min(25, $limit)),
            'direction' => preg_match('/\b(worst|lowest|weakest|underperform|least|bottom)\b/i', $text)
                ? 'asc' : 'desc',
            'raw' => $q,
        ];
    }

    /* --------------------------------------------------------- resolvers */

    private function answer(array $o): array
    {
        return array_merge([
            'kind' => 'metric',
            'headline' => null,
            'sub' => null,
            'change' => null,
            'narrative' => null,
            /** Set only by resolveFinding — the single thing to do about it. */
            'todo' => null,
            'confidence' => 0.85,
            'factors' => [],
            'items' => [],
            'chart' => null,
            'table' => null,
            'drill' => null,
        ], $o);
    }

    private function b(string $s): string
    {
        return '**'.$s.'**';
    }

    private function resolveHelp(): array
    {
        return $this->answer([
            'kind' => 'list',
            'headline' => 'Here is what I can answer',
            'narrative' => 'I read directly from your live data, so every number below is computed '
                .'at the moment you ask. I cannot answer things outside this dataset — when that '
                .'happens I will tell you rather than guess.',
            'items' => array_map(fn ($c) => ['title' => $c], self::CAPABILITIES),
            'confidence' => 1.0,
        ]);
    }

    private function resolveRisk(): array
    {
        $insights = array_slice($this->engine->live(), 0, 5);

        if (! $insights) {
            return $this->answer([
                'kind' => 'list',
                'headline' => 'Nothing is flagged right now',
                'narrative' => 'Every detector came back clean against current data. That is a real '
                    .'result, not a placeholder — ingest new data and this will change.',
            ]);
        }

        return $this->answer([
            'kind' => 'list',
            'headline' => count($insights).' things need your attention',
            'narrative' => 'Ranked by severity × confidence × business impact for your role. '
                .'The top one is '.$this->b($insights[0]->title).'.',
            'items' => array_map(fn ($i) => [
                'title' => $i->title,
                'band' => $i->band,
                'area' => $i->area,
                'route' => route('app.insights', ['id' => $i->id]),
            ], $insights),
            'drill' => ['label' => 'Open all findings', 'route' => route('app.insights')],
            'confidence' => 0.95,
        ]);
    }

    private function resolveRecommend(): array
    {
        $recs = collect($this->engine->live())
            ->flatMap(fn ($i) => $i->recommendations
                ->where('is_primary', true)
                ->whereNull('done_at')
                ->map(fn ($r) => ['rec' => $r, 'insight' => $i]))
            ->sortByDesc(fn ($x) => $x['rec']->impact)
            ->take(6)
            ->values();

        if ($recs->isEmpty()) {
            return $this->answer([
                'kind' => 'list',
                'headline' => 'No outstanding recommendations',
                'narrative' => 'Every primary recommendation currently on the board has been actioned.',
            ]);
        }

        $total = $recs->sum(fn ($x) => (float) $x['rec']->impact);

        return $this->answer([
            'kind' => 'list',
            'headline' => $recs->count().' actions worth roughly '.Fmt::moneyShort($total),
            'narrative' => 'These are the primary recommendations attached to your highest-priority '
                .'findings, ordered by estimated impact. Each one is executable from its card.',
            'items' => $recs->map(fn ($x) => [
                'title' => $x['rec']->label,
                'note' => $x['rec']->rationale,
                'value' => $x['rec']->impact > 0 ? Fmt::moneyShort($x['rec']->impact) : null,
                'area' => $x['insight']->area,
                'route' => route('app.insights', ['id' => $x['insight']->id]),
            ])->all(),
            'drill' => ['label' => 'Open all findings', 'route' => route('app.insights')],
        ]);
    }

    private function resolveRanking(array $p): array
    {
        $asc = $p['direction'] === 'asc';
        $flip = fn ($c) => $asc ? $c->reverse()->values() : $c->values();

        /* --- agents: subject wins over metric --------------------------- */
        if ($p['subject'] === 'agent' || $p['metric'] === 'agents' || $p['dim'] === 'agent') {
            $a = $this->metrics->agents();
            $rows = $flip(collect($a['rows'])->filter(fn ($x) => $x['leads'] >= 15)
                ->sortByDesc('revenue')->values())->take($p['limit']);

            if ($rows->isEmpty()) {
                return $this->resolveUnknown($p);
            }

            $t = $rows->first();

            return $this->answer([
                'kind' => 'ranking',
                'headline' => ($asc ? 'Lowest ' : 'Top ').$rows->count().' agents by revenue',
                'narrative' => $this->b($t['name']).' leads with '.$this->b(Fmt::moneyShort($t['revenue']))
                    .' across '.$this->b(Fmt::num($t['won'])).' closed deals at a '
                    .$this->b(Fmt::pct($t['closeRate'], 0)).' close rate. Team median close rate is '
                    .$this->b(Fmt::pct($a['baselineCloseRate'], 0)).'.',
                'table' => [
                    'columns' => ['Agent', 'Revenue', 'Deals', 'Close rate', 'Open leads'],
                    'rows' => $rows->map(fn ($x) => [
                        $x['name'], Fmt::moneyShort($x['revenue']), Fmt::num($x['won']),
                        Fmt::pct($x['closeRate'], 0), Fmt::num($x['openLeads']),
                    ])->all(),
                ],
                'chart' => ['kind' => 'bar', 'payload' => [
                    'data' => $rows->map(fn ($x) => [
                        'label' => explode(' ', $x['name'])[0], 'value' => $x['revenue'],
                    ])->all(),
                    'opts' => ['format' => 'money', 'height' => 200],
                ]],
                'drill' => ['label' => 'Open the team table', 'route' => route('app.sales', ['view' => 'agents'])],
            ]);
        }

        /* --- locations / inventory -------------------------------------- */
        if ($p['subject'] === 'location' || $p['subject'] === 'property'
            || (! $p['subject'] && ($p['metric'] === 'inventory' || $p['dim'] === 'location' || $p['location']))) {
            $rows = $flip(collect($this->metrics->properties()['byLocation']))->take($p['limit']);
            $t = $rows->first();

            return $this->answer([
                'kind' => 'ranking',
                'headline' => ($asc ? 'Weakest' : 'Strongest').' locations by inventory',
                'narrative' => $this->b($t['key']).' holds '.$this->b(Fmt::num($t['listings']))
                    .' listings worth '.$this->b(Fmt::moneyShort($t['value'])).', with a '
                    .$this->b(Fmt::pct($t['sellThrough'], 0)).' sell-through and '
                    .$this->b(round($t['avgDom']).' days').' average time on market.',
                'table' => [
                    'columns' => ['Location', 'Listings', 'Available', 'Sold', 'Sell-through', 'Avg DOM'],
                    'rows' => $rows->map(fn ($r) => [
                        $r['key'], Fmt::num($r['listings']), Fmt::num($r['available']),
                        Fmt::num($r['sold']), Fmt::pct($r['sellThrough'], 0), round($r['avgDom']).'d',
                    ])->all(),
                ],
                'chart' => ['kind' => 'bar', 'payload' => [
                    'data' => $rows->map(fn ($r) => ['label' => $r['key'], 'value' => $r['value']])->all(),
                    'opts' => ['format' => 'money', 'height' => 200],
                ]],
                'drill' => ['label' => 'Open properties', 'route' => route('app.properties')],
            ]);
        }

        /* --- campaigns --------------------------------------------------- */
        if ($p['subject'] === 'campaign'
            || (! $p['subject'] && in_array($p['metric'], ['spend', 'roas'], true))) {
            $mk = $this->metrics->marketing();
            $rows = $flip(collect($mk['campaigns'])->filter(fn ($c) => $c['leads'] >= 8)
                ->sortByDesc('roas')->values())->take($p['limit']);

            if ($rows->isEmpty()) {
                return $this->resolveUnknown($p);
            }

            $t = $rows->first();

            return $this->answer([
                'kind' => 'ranking',
                'headline' => ($asc ? 'Worst' : 'Best').' campaigns by return on spend',
                'narrative' => $this->b($t['name']).' is returning '
                    .$this->b(number_format($t['roas'], 2).'×').' on '.$this->b(Fmt::moneyShort($t['spend']))
                    .' of spend, producing '.$this->b(Fmt::num($t['leads'])).' leads at '
                    .$this->b(Fmt::money(round($t['cpl']))).' each. Portfolio average is '
                    .$this->b(number_format($mk['roas'], 2).'×').'.',
                'table' => [
                    'columns' => ['Campaign', 'Spend', 'Leads', 'Closed', 'CPL', 'ROAS'],
                    'rows' => $rows->map(fn ($c) => [
                        $c['name'], Fmt::moneyShort($c['spend']), Fmt::num($c['leads']),
                        Fmt::num($c['won']), Fmt::money(round($c['cpl'])),
                        number_format($c['roas'], 2).'×',
                    ])->all(),
                ],
                'drill' => ['label' => 'Open marketing', 'route' => route('app.marketing')],
            ]);
        }

        /* --- default: lead sources --------------------------------------- */
        $rows = $flip(collect($this->metrics->leads()['bySource'])->sortByDesc('conversion')->values())
            ->take($p['limit']);
        $t = $rows->first();

        return $this->answer([
            'kind' => 'ranking',
            'headline' => ($asc ? 'Weakest' : 'Best').' lead sources by conversion',
            'narrative' => $this->b($t['key']).' converts at '.$this->b(Fmt::pct($t['conversion'], 1))
                .' across '.$this->b(Fmt::num($t['leads'])).' leads, with an average score of '
                .$this->b((string) round($t['avgScore'])).'.',
            'table' => [
                'columns' => ['Source', 'Leads', 'Closed', 'Conversion', 'Avg score', 'Open pipeline'],
                'rows' => $rows->map(fn ($r) => [
                    $r['key'], Fmt::num($r['leads']), Fmt::num($r['won']),
                    Fmt::pct($r['conversion'], 1), round($r['avgScore']), Fmt::moneyShort($r['pipeline']),
                ])->all(),
            ],
            'chart' => ['kind' => 'bar', 'payload' => [
                'data' => $rows->map(fn ($r) => ['label' => $r['key'], 'value' => $r['conversion']])->all(),
                'opts' => ['format' => 'pct', 'height' => 200],
            ]],
            'drill' => ['label' => 'Open leads', 'route' => route('app.leads')],
        ]);
    }

    private function resolveValue(array $p): ?array
    {
        if (! $p['metric']) {
            return null;
        }

        $m = $this->metrics;
        $b = fn ($s) => $this->b($s);
        $w = $p['windowLabel'];

        $build = match ($p['metric']) {
            'revenue' => function () use ($m, $b, $w, $p) {
                $s = $m->sales($p['days']);

                return [
                    'headline' => Fmt::moneyShort($s['revenue']),
                    'sub' => 'revenue in '.$w,
                    'change' => $s['revenueChange'],
                    'narrative' => $b(Fmt::moneyShort($s['revenue'])).' closed across '
                        .$b(Fmt::num($s['deals'])).' deals in '.$w.', '.$b(Fmt::delta($s['revenueChange']))
                        .' against the prior period. Average deal size '.$b(Fmt::moneyShort($s['avgDeal']))
                        .', win rate '.$b(Fmt::pct($s['winRate'], 0)).', average cycle '
                        .$b(round($s['cycleDays']).' days').'.',
                    'chart' => ['kind' => 'line', 'payload' => [
                        'series' => [['name' => 'Revenue', 'data' => $s['series']]],
                        'opts' => ['format' => 'money', 'height' => 210],
                    ]],
                    'drill' => ['label' => 'Open sales', 'route' => route('app.sales')],
                ];
            },

            'deals' => function () use ($m, $b, $w, $p) {
                $s = $m->sales($p['days']);

                return [
                    'headline' => Fmt::num($s['deals']),
                    'sub' => 'deals closed in '.$w,
                    'change' => $s['dealsChange'],
                    'narrative' => $b(Fmt::num($s['deals'])).' deals closed in '.$w.' ('
                        .$b(Fmt::delta($s['dealsChange'])).'), worth '.$b(Fmt::moneyShort($s['revenue']))
                        .' and generating '.$b(Fmt::moneyShort($s['commission'])).' in commission.',
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => $s['countSeries'],
                        'opts' => ['format' => 'count', 'height' => 200],
                    ]],
                    'drill' => ['label' => 'Open sales', 'route' => route('app.sales')],
                ];
            },

            'leads' => function () use ($m, $b, $w, $p) {
                $l = $m->leads($p['days']);

                return [
                    'headline' => Fmt::num($l['new']),
                    'sub' => 'new leads in '.$w,
                    'change' => $l['newChange'],
                    'narrative' => $b(Fmt::num($l['new'])).' new leads in '.$w.', '
                        .$b(Fmt::delta($l['newChange'])).' on the prior period. You are holding '
                        .$b(Fmt::num($l['open'])).' open leads carrying '.$b(Fmt::moneyShort($l['pipeline']))
                        .' of pipeline, of which '.$b(Fmt::num($l['hot'])).' score 70 or above.',
                    'chart' => ['kind' => 'line', 'payload' => [
                        'series' => [['name' => 'Leads', 'color' => 'var(--c3)', 'data' => $l['series']]],
                        'opts' => ['format' => 'count', 'height' => 210],
                    ]],
                    'drill' => ['label' => 'Open leads', 'route' => route('app.leads')],
                ];
            },

            'pipeline' => function () use ($m, $b) {
                $l = $m->leads();

                return [
                    'headline' => Fmt::moneyShort($l['pipeline']),
                    'sub' => 'open pipeline',
                    'narrative' => $b(Fmt::moneyShort($l['pipeline'])).' across '.$b(Fmt::num($l['open']))
                        .' open leads. '.$b(Fmt::num($l['hot'])).' are high-intent (score 70+), and '
                        .$b(Fmt::num($l['stale'])).' have gone quiet for two weeks or more — that '
                        .'portion is worth '.$b(Fmt::moneyShort($l['stalePipeline'])).'.',
                    'drill' => ['label' => 'Open leads', 'route' => route('app.leads')],
                ];
            },

            'conversion' => function () use ($m, $b) {
                $s = $m->sales(90);
                $l = $m->leads();
                $best = collect($l['bySource'])->sortByDesc('conversion')->first();

                return [
                    'headline' => Fmt::pct($s['winRate'], 1),
                    'sub' => 'win rate',
                    'change' => $s['winRateChange'],
                    'narrative' => 'You are closing '.$b(Fmt::pct($s['winRate'], 1)).' of decided deals ('
                        .$b(Fmt::pts($s['winRateChange'], 1)).' on the prior period). Lead-to-close '
                        .'conversion across all sources is '.$b(Fmt::pct($l['conversion'], 1))
                        .', and the best channel is '.$b($best['key'] ?? '—').'.',
                    'drill' => ['label' => 'Open the funnel', 'route' => route('app.sales', ['view' => 'funnel'])],
                ];
            },

            'inventory' => function () use ($m, $b) {
                $pr = $m->properties();

                return [
                    'headline' => Fmt::moneyShort($pr['inventoryValue']),
                    'sub' => 'across '.Fmt::num($pr['available']).' available listings',
                    'narrative' => $b(Fmt::num($pr['available'])).' available listings worth '
                        .$b(Fmt::moneyShort($pr['inventoryValue'])).', plus '.$b(Fmt::num($pr['reserved']))
                        .' reserved. Median time on market is '.$b(round($pr['domMedian']).' days')
                        .' and '.$b(Fmt::num($pr['stale'])).' listings have run past twice that.',
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($pr['byLocation'])->take(8)
                            ->map(fn ($r) => ['label' => $r['key'], 'value' => $r['available']])->values()->all(),
                        'opts' => ['format' => 'count', 'height' => 200, 'color' => 'var(--c3)'],
                    ]],
                    'drill' => ['label' => 'Open properties', 'route' => route('app.properties')],
                ];
            },

            'overdue' => function () use ($m, $b) {
                $f = $m->finance();

                return [
                    'headline' => Fmt::moneyShort($f['overdueAmount']),
                    'sub' => 'overdue across '.Fmt::num($f['atRiskAccounts']).' accounts',
                    'narrative' => $b(Fmt::moneyShort($f['overdueAmount'])).' is overdue across '
                        .$b(Fmt::num($f['overdueCount'])).' instalments and '.$b(Fmt::num($f['atRiskAccounts']))
                        .' accounts. '.$b(Fmt::moneyShort($f['aging']['90+'] ?? 0)).' has aged beyond 90 '
                        .'days. Total outstanding including not-yet-late is '
                        .$b(Fmt::moneyShort($f['outstanding'])).'.',
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($f['aging'])->map(fn ($v, $k) => ['label' => $k, 'value' => $v])
                            ->values()->all(),
                        'opts' => ['format' => 'money', 'height' => 200, 'color' => 'var(--c4)'],
                    ]],
                    'drill' => ['label' => 'Open receivables', 'route' => route('app.finance', ['filter' => 'overdue'])],
                ];
            },

            'collections' => function () use ($m, $b) {
                $f = $m->finance();

                return [
                    'headline' => Fmt::pct($f['collectionRatio'], 0),
                    'sub' => 'collection ratio',
                    'narrative' => $b(Fmt::moneyShort($f['collected'])).' collected against '
                        .$b(Fmt::moneyShort($f['outstanding'])).' outstanding — a '
                        .$b(Fmt::pct($f['collectionRatio'], 0)).' collection ratio. '
                        .$b(Fmt::moneyShort($f['expected30'])).' falls due in the next 30 days.',
                    'chart' => ['kind' => 'line', 'payload' => [
                        'series' => [['name' => 'Collected', 'data' => $f['collectedSeries']]],
                        'opts' => ['format' => 'money', 'height' => 210],
                    ]],
                    'drill' => ['label' => 'Open finance', 'route' => route('app.finance')],
                ];
            },

            'stale' => function () use ($m, $b) {
                $l = $m->leads();

                return [
                    'headline' => Fmt::num($l['stale']),
                    'sub' => 'leads with no contact in 14+ days',
                    'narrative' => $b(Fmt::num($l['stale'])).' open leads have gone untouched past 14 '
                        .'days, carrying '.$b(Fmt::moneyShort($l['stalePipeline'])).' of pipeline. A normal '
                        .'backlog at your volume would be about '.$b(Fmt::num($l['staleBaseline'])).'.',
                    'drill' => ['label' => 'Open stale leads', 'route' => route('app.leads', ['filter' => 'stale'])],
                ];
            },

            'satisfaction' => function () use ($m, $b) {
                $fb = $m->feedback();
                $top = $fb['tags'][0] ?? null;

                return [
                    'headline' => number_format($fb['avgRating'], 2),
                    'sub' => 'average rating, last 60 days',
                    'change' => $fb['ratingChange'] / 5,
                    'narrative' => 'Average rating is '.$b(number_format($fb['avgRating'], 2)).' across '
                        .$b(Fmt::num($fb['window'])).' reviews in the last 60 days, from '
                        .$b(number_format($fb['prevRating'], 2)).' in the prior 60. '
                        .$b(Fmt::num($fb['negative'])).' were one or two stars.'
                        .($top ? ' The most-cited theme is '.$b('"'.$top['key'].'"').' with '
                            .$b(Fmt::num($top['count'])).' mentions.' : ''),
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($fb['distribution'])->map(fn ($d) => [
                            'label' => $d['rating'].'★', 'value' => $d['count'],
                        ])->all(),
                        'opts' => ['format' => 'count', 'height' => 200],
                    ]],
                    'drill' => ['label' => 'Open investors', 'route' => route('app.customers')],
                ];
            },

            'complaints' => function () use ($m, $b) {
                $fb = $m->feedback();
                $themes = collect($fb['tags'])->take(3)
                    ->map(fn ($t) => $this->b($t['key']).' ('.$t['count'].')')->implode(', ');

                return [
                    'headline' => Fmt::num($fb['negative']),
                    'sub' => 'negative reviews in 60 days',
                    'narrative' => $b(Fmt::num($fb['negative'])).' of '.$b(Fmt::num($fb['window']))
                        .' reviews in the last 60 days were one or two stars — a '
                        .$b(Fmt::pct($fb['negativeRate'], 0)).' negative rate. Top themes: '.$themes.'.',
                    'drill' => ['label' => 'Open investors', 'route' => route('app.customers')],
                ];
            },

            'spend' => function () use ($m, $b) {
                $mk = $m->marketing();

                return [
                    'headline' => Fmt::moneyShort($mk['spend']),
                    'sub' => 'total marketing spend',
                    'narrative' => $b(Fmt::moneyShort($mk['spend'])).' spent producing '
                        .$b(Fmt::num($mk['leads'])).' leads and '.$b(Fmt::num($mk['won']))
                        .' closed deals worth '.$b(Fmt::moneyShort($mk['revenue'])).'. That is '
                        .$b(Fmt::money(round($mk['cpl']))).' per lead, '.$b(Fmt::money(round($mk['cac'])))
                        .' per customer, and '.$b(number_format($mk['roas'], 2).'×').' on spend.',
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($mk['byChannel'])->map(fn ($c) => [
                            'label' => $c['key'], 'value' => $c['spend'],
                        ])->values()->all(),
                        'opts' => ['format' => 'money', 'height' => 200],
                    ]],
                    'drill' => ['label' => 'Open marketing', 'route' => route('app.marketing')],
                ];
            },

            'roas' => function () use ($m, $b) {
                $mk = $m->marketing();
                $roasList = collect($mk['byChannel'])->pluck('roas');

                return [
                    'headline' => number_format($mk['roas'], 2).'×',
                    'sub' => 'return on ad spend',
                    'narrative' => $b(Fmt::moneyShort($mk['revenue'])).' of attributed revenue on '
                        .$b(Fmt::moneyShort($mk['spend'])).' of spend — '
                        .$b(number_format($mk['roas'], 2).'×').'. The spread across channels runs from '
                        .$b(number_format($roasList->min(), 1).'×').' to '
                        .$b(number_format($roasList->max(), 1).'×').'.',
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($mk['byChannel'])->map(fn ($c) => [
                            'label' => $c['key'], 'value' => round($c['roas'], 2),
                        ])->values()->all(),
                        'opts' => ['format' => 'number', 'height' => 200],
                    ]],
                    'drill' => ['label' => 'Open marketing', 'route' => route('app.marketing')],
                ];
            },

            'cac' => function () use ($m, $b) {
                $mk = $m->marketing();
                $s = $m->sales(90);
                $c = $m->customers();

                return [
                    'headline' => Fmt::money(round($mk['cac'])),
                    'sub' => 'customer acquisition cost',
                    'narrative' => $b(Fmt::money(round($mk['cac']))).' to acquire a customer, against an '
                        .'average first deal of '.$b(Fmt::moneyShort($s['avgDeal'])).' and an average '
                        .'lifetime value of '.$b(Fmt::moneyShort($c['avgLtv'])).'.',
                    'drill' => ['label' => 'Open marketing', 'route' => route('app.marketing')],
                ];
            },

            'cpl' => function () use ($m, $b) {
                $mk = $m->marketing();

                return [
                    'headline' => Fmt::money(round($mk['cpl'])),
                    'sub' => 'cost per lead',
                    'narrative' => $b(Fmt::money(round($mk['cpl']))).' per lead across '
                        .$b(Fmt::num($mk['leads'])).' campaign-attributed leads. Median campaign cost per '
                        .'lead is '.$b(Fmt::money(round($mk['baselineCpl']))).'.',
                    'drill' => ['label' => 'Open marketing', 'route' => route('app.marketing')],
                ];
            },

            'dom' => function () use ($m, $b) {
                $pr = $m->properties();
                $fastest = collect($pr['byLocation'])->filter(fn ($x) => $x['sold'] > 3)
                    ->sortBy('avgDom')->first();

                return [
                    'headline' => round($pr['avgDom']).' days',
                    'sub' => 'average time to sell',
                    'narrative' => 'Sold properties averaged '.$b(round($pr['avgDom']).' days')
                        .' on market (median '.$b(round($pr['domMedian']).' days').').'
                        .($fastest ? ' Fastest-moving location is '.$b($fastest['key']).'.' : ''),
                    'chart' => ['kind' => 'bar', 'payload' => [
                        'data' => collect($pr['byLocation'])->filter(fn ($r) => $r['sold'] > 2)
                            ->map(fn ($r) => ['label' => $r['key'], 'value' => round($r['avgDom'])])
                            ->values()->all(),
                        'opts' => ['format' => 'count', 'height' => 200, 'color' => 'var(--c4)'],
                    ]],
                    'drill' => ['label' => 'Open properties', 'route' => route('app.properties')],
                ];
            },

            'customers' => function () use ($m, $b) {
                $c = $m->customers();

                return [
                    'headline' => Fmt::num($c['total']),
                    'sub' => 'customers',
                    'narrative' => $b(Fmt::num($c['total'])).' customers with '
                        .$b(Fmt::moneyShort($c['ltv'])).' in combined lifetime value, averaging '
                        .$b(Fmt::moneyShort($c['avgLtv'])).' each. '.$b(Fmt::num($c['repeat']))
                        .' have bought more than once. Average satisfaction '
                        .$b(number_format($c['satisfaction'], 2)).', with '.$b(Fmt::num($c['promoters']))
                        .' promoters and '.$b(Fmt::num($c['detractors'])).' detractors.',
                    'drill' => ['label' => 'Open customers', 'route' => route('app.customers')],
                ];
            },

            /* ------------------------------------------- the instalment product
               These four read the platform tables directly rather than going
               through the metrics layer, which predates the pivot and has no
               group for any of them. They are cheap counts on indexed columns,
               and the alternative — a tenth metric group — is a much larger
               change to make in service of four questions. */

            'commitments' => function () use ($b) {
                $counts = Deal::query()->whereNotNull('committed_at')
                    ->selectRaw('status, COUNT(*) c, COALESCE(SUM(value),0) v')
                    ->groupBy('status')->get()->keyBy('status');

                $n = fn ($s) => (int) ($counts[$s]->c ?? 0);
                $v = fn ($s) => (float) ($counts[$s]->v ?? 0);
                $pending = $n('pending');
                $live = $n('approved') + $n('active');

                // The blocked count is the number worth surfacing: a pending
                // commitment whose investor has no realtor cannot be approved
                // at all, and it is invisible in a plain status breakdown.
                $blocked = Deal::query()->where('status', 'pending')
                    ->whereHas('customer', fn ($q) => $q->whereNull('agent_id'))->count();

                return [
                    'headline' => Fmt::num($pending),
                    'sub' => 'commitments awaiting approval',
                    'narrative' => $b(Fmt::num($pending)).' '
                        .($pending === 1 ? 'commitment is' : 'commitments are').' waiting for approval, '
                        .'worth '.$b(Fmt::moneyShort($v('pending'))).'. '
                        .($blocked > 0
                            ? $b(Fmt::num($blocked)).' of them cannot be approved yet because the '
                              .'investor has no realtor attached — assign one and the block clears. '
                            : 'None of them are blocked. ')
                        .$b(Fmt::num($live)).' '.($live === 1 ? 'plan is' : 'plans are')
                        .' live and paying, and '.$b(Fmt::num($n('completed'))).' have settled in full.',
                    'drill' => ['label' => 'Open the approval queue', 'route' => route('app.commitments')],
                ];
            },

            'unassigned' => function () use ($b) {
                $unassigned = Customer::query()->unassigned()->count();
                $total = Customer::count();

                return [
                    'headline' => Fmt::num($unassigned),
                    'sub' => 'investors with no realtor attached',
                    'narrative' => $unassigned === 0
                        ? 'Every one of your '.$b(Fmt::num($total)).' investors has a realtor '
                          .'attached, so nothing is blocked at approval.'
                        : $b(Fmt::num($unassigned)).' of '.$b(Fmt::num($total)).' investors ('
                          .$b(Fmt::pct(Stats::safeDiv($unassigned, $total), 1))
                          .') arrived without a referral code and have nobody attached. That is not '
                          .'missing data — it is the queue. None of them can have a commitment '
                          .'approved until a realtor is assigned, because commission has to have an '
                          .'owner.',
                    'drill' => ['label' => 'Open the unassigned queue',
                        'route' => route('app.customers', ['filter' => 'unassigned'])],
                ];
            },

            'instalments' => function () use ($b) {
                $paid = (int) Payment::whereNotNull('paid_at')->count();
                $collected = (float) Payment::whereNotNull('paid_at')->sum('amount');
                $scheduled = (int) Payment::whereNull('paid_at')->count();
                $remaining = (float) Payment::whereNull('paid_at')->sum('amount');
                $lateCount = (int) Payment::whereNull('paid_at')->where('due_at', '<=', now())->count();

                return [
                    'headline' => Fmt::num($paid),
                    'sub' => 'instalments recorded',
                    'narrative' => $b(Fmt::num($paid)).' instalments totalling '
                        .$b(Fmt::moneyShort($collected)).' have been received. '
                        .$b(Fmt::num($scheduled)).' remain scheduled, worth '
                        .$b(Fmt::moneyShort($remaining)).', of which '.$b(Fmt::num($lateCount))
                        .' '.($lateCount === 1 ? 'is' : 'are').' already due or past due.',
                    'drill' => ['label' => 'Open finance', 'route' => route('app.finance')],
                ];
            },

            'realtors' => function () use ($m, $b) {
                $approved = Agent::query()->approved()->count();
                $pending = Agent::query()->pending()->count();
                $a = $m->agents();
                $top = collect($a['rows'])->sortByDesc('revenue')->first();

                return [
                    'headline' => Fmt::num($approved),
                    'sub' => 'approved realtors',
                    'narrative' => $b(Fmt::num($approved)).' realtors are approved and able to bring '
                        .'buyers in'
                        .($pending > 0
                            ? ', and '.$b(Fmt::num($pending)).' '.($pending === 1 ? 'is' : 'are')
                              .' waiting on approval — until that happens they have no code and no '
                              .'link, so they cannot capture anybody'
                            : ', with nobody waiting on approval')
                        .'. Median close rate across the book is '
                        .$b(Fmt::pct($a['baselineCloseRate'], 0))
                        .($top ? ', and the strongest producer is '.$b($top['name']).' on '
                            .$b(Fmt::moneyShort($top['revenue'])) : '').'.',
                    'drill' => ['label' => 'Open realtors', 'route' => route('app.realtors')],
                ];
            },

            'agents' => function () use ($m, $b) {
                $a = $m->agents();

                return [
                    'headline' => Fmt::num(count($a['rows'])),
                    'sub' => 'agents',
                    'narrative' => $b(Fmt::num(count($a['rows']))).' agents producing '
                        .$b(Fmt::moneyShort($a['totalRevenue'])).'. Median close rate is '
                        .$b(Fmt::pct($a['baselineCloseRate'], 0)).', and the top three account for '
                        .$b(Fmt::pct($a['concentration'], 0)).' of revenue.',
                    'drill' => ['label' => 'Open the team table', 'route' => route('app.sales', ['view' => 'agents'])],
                ];
            },

            default => null,
        };

        return $build ? $this->answer($build()) : null;
    }

    private function resolveTrend(array $p): ?array
    {
        $r = $this->resolveValue($p);

        if (! $r) {
            return null;
        }

        $series = collect($r['chart']['payload']['series'][0]['data'] ?? $r['chart']['payload']['data'] ?? [])
            ->pluck('value')->filter(fn ($v) => is_numeric($v))->values()->all();

        $t = count($series) >= 4 ? Stats::trendPct($series) : 0.0;

        $verdict = $t > 0.04 ? 'a genuine upward trajectory'
            : ($t < -0.04 ? 'a genuine decline, not noise' : 'essentially flat');

        return $this->answer([
            ...$r,
            'kind' => 'trend',
            'narrative' => $r['narrative'].' Over the full series the trend is '
                .$this->b(($t >= 0 ? '+' : '').number_format($t * 100, 1).'% per period')
                .', which is '.$verdict.'.',
        ]);
    }

    /**
     * The full explanation of one finding.
     *
     * Everything the engine knows about it, assembled into a single answer:
     * what was detected, the narrated figures behind it, what to do and why
     * that is the recommendation, what else is going on in the same area, and
     * the evidence with its drill-throughs intact.
     *
     * This is the deep end of "Find out more" — the card said three sentences,
     * and this says all of it.
     */
    private function resolveFinding(int $id): ?array
    {
        $insight = \App\Models\Insight::with('recommendations')->find($id);

        if (! $insight || ! $insight->isLive()) {
            return null;
        }

        $rec = $insight->primaryRecommendation();

        // Carried as its own field rather than appended to the narrative:
        // Fmt::rich collapses all whitespace, so a paragraph break inside the
        // prose would have arrived as a single space and run the advice on to
        // the end of the explanation.
        $todo = $rec ? [
            'label' => $rec->label,
            'why' => $rec->rationale,
            'impact' => $rec->impact > 0 ? Fmt::moneyShort($rec->impact) : null,
        ] : null;

        // Everything else flagged in the same area. A finding rarely stands
        // alone, and the neighbouring ones are usually the context that
        // explains it.
        $siblings = collect($this->engine->live($insight->area))
            ->reject(fn ($i) => $i->id === $insight->id)
            ->take(4);

        return $this->answer([
            'kind' => 'causal',
            'headline' => $insight->title,
            'sub' => (InsightEngine::AREAS[$insight->area] ?? $insight->area)
                .' · '.Fmt::titleCase($insight->detector).' detector',
            'narrative' => $this->narrator->narrate($insight),
            'todo' => $todo,
            // The evidence, as the contributing factors — each keeping the
            // drill-through it carried on the card.
            'factors' => collect($insight->evidence ?? [])->map(fn ($e) => [
                'label' => $e['label'],
                'value' => Fmt::evidence($e),
                'note' => null,
                'route' => isset($e['drill'])
                    ? route(InsightEngine::areaRoute($e['drill']['route']), $e['drill']['params'] ?? [])
                    : null,
            ])->all(),
            'items' => $siblings->map(fn ($i) => [
                'title' => $i->title,
                'band' => $i->band,
                'area' => $i->area,
                'route' => route('app.insights', ['id' => $i->id]),
            ])->all(),
            'drill' => ['label' => 'Open this finding', 'route' => route('app.insights', ['id' => $insight->id])],
            'confidence' => (float) $insight->confidence,
        ]);
    }

    /**
     * A question that names one channel, answered about that channel.
     *
     * "Why is Billboard converting worse than my other sources" used to fall
     * through to the generic leads answer, which reported total lead volume
     * and never mentioned Billboard — a confident reply to a question nobody
     * asked. The entity was extracted during classification all along; nothing
     * downstream was reading it.
     */
    private function resolveSource(array $p): ?array
    {
        if (! $p['source']) {
            return null;
        }

        $l = $this->metrics->leads();
        $rows = collect($l['bySource']);
        $row = $rows->firstWhere('key', $p['source']);

        if (! $row || ! $row['leads']) {
            return null;
        }

        $median = Stats::median($rows->pluck('conversion')->map(fn ($v) => (float) $v)->all());
        $best = $rows->sortByDesc('conversion')->first();
        $b = fn ($s) => $this->b($s);
        $share = Stats::safeDiv($row['leads'], max(1, $l['total']));

        return $this->answer([
            'kind' => 'causal',
            'headline' => Fmt::pct($row['conversion'], 2),
            'sub' => $p['source'].' conversion',
            'narrative' => $b($p['source']).' has produced '.$b(Fmt::num($row['leads']))
                .' leads — '.$b(Fmt::pct($share, 0)).' of everything you have captured — and closed '
                .$b(Fmt::num($row['won'])).' of them, a '.$b(Fmt::pct($row['conversion'], 2))
                .' conversion rate against a median of '.$b(Fmt::pct($median, 2))
                .' across your channels. Its average lead score is '
                .$b((string) round($row['avgScore'])).', and it is still holding '
                .$b(Fmt::moneyShort($row['pipeline'])).' of open pipeline. Your strongest channel is '
                .$b($best['key']).' at '.$b(Fmt::pct($best['conversion'], 2)).'.',
            'table' => [
                'columns' => ['Source', 'Leads', 'Won', 'Conversion', 'Open pipeline'],
                'rows' => $rows->sortByDesc('leads')->take(10)->map(fn ($r) => [
                    $r['key'], Fmt::num($r['leads']), Fmt::num($r['won']),
                    Fmt::pct($r['conversion'], 2), Fmt::moneyShort($r['pipeline']),
                ])->values()->all(),
            ],
            'chart' => ['kind' => 'bar', 'payload' => [
                'data' => $rows->sortByDesc('conversion')->map(fn ($r) => [
                    'label' => $r['key'],
                    'value' => round($r['conversion'] * 100, 2),
                    'color' => $r['key'] === $p['source'] ? 'var(--c2)' : 'var(--c1)',
                ])->values()->all(),
                'opts' => ['format' => 'number', 'height' => 210],
            ]],
            'drill' => ['label' => 'Open leads', 'route' => route('app.leads')],
            'confidence' => 0.88,
        ]);
    }

    private function resolveCausal(array $p): ?array
    {
        // A named channel outranks the generic area answer.
        if ($named = $this->resolveSource($p)) {
            return $named;
        }

        $area = $this->metricToArea($p['metric']);
        $related = collect($this->engine->live())
            ->filter(fn ($i) => ! $area || $i->area === $area)
            ->values();

        $base = $this->resolveValue($p);

        if ($related->isEmpty()) {
            return $base ? $this->answer([
                ...$base,
                'kind' => 'causal',
                'narrative' => $base['narrative'].' No detector has flagged a specific cause for this '
                    .'movement — it is currently within normal variation.',
            ]) : null;
        }

        $top = $related->first();

        return $this->answer([
            'kind' => 'causal',
            'headline' => $base['headline'] ?? $top->title,
            'sub' => $base['sub'] ?? null,
            'change' => $base['change'] ?? null,
            'chart' => $base['chart'] ?? null,
            'narrative' => ($base ? $base['narrative'].' ' : '')
                .'The strongest contributing signal is: '.$this->b($top->title),
            'factors' => $related->take(4)->map(fn ($i) => [
                'label' => $i->title,
                'value' => $i->band,
                'note' => InsightEngine::AREAS[$i->area] ?? $i->area,
                'route' => route('app.insights', ['id' => $i->id]),
            ])->all(),
            'drill' => $base['drill'] ?? ['label' => 'Open the finding', 'route' => route('app.insights', ['id' => $top->id])],
            'confidence' => 0.78,
        ]);
    }

    private function resolveForecast(): ?array
    {
        $s = $this->metrics->sales(90);
        $series = array_slice(array_column($s['series'], 'value'), -6);

        if (count($series) < 4) {
            return null;
        }

        $t = Stats::trendPct($series);
        $last3 = array_sum(array_slice($series, -3)) / 3;
        $projected = $last3 * (1 + $t);

        return $this->answer([
            'headline' => Fmt::moneyShort($projected),
            'sub' => 'projected revenue, next month',
            'narrative' => 'Extrapolating the trailing six-month trend ('
                .$this->b(number_format($t * 100, 1).'% per month').') off a three-month average of '
                .$this->b(Fmt::moneyShort($last3)).' gives roughly '.$this->b(Fmt::moneyShort($projected))
                .' next month, with a realistic range of '.$this->b(Fmt::moneyShort($projected * 0.82))
                .' to '.$this->b(Fmt::moneyShort($projected * 1.18)).'. This is a linear projection on '
                .'historical closes — it does not model your current pipeline of '
                .$this->b(Fmt::moneyShort($this->metrics->leads()['pipeline'])).', seasonality, or the '
                .$this->b(Fmt::num(count($this->engine->live()))).' open risks flagged elsewhere.',
            'chart' => ['kind' => 'line', 'payload' => [
                'series' => [['name' => 'Revenue', 'data' => $s['series']]],
                'opts' => ['format' => 'money', 'height' => 210],
            ]],
            'drill' => ['label' => 'Open analytics', 'route' => route('app.analytics')],
            // Deliberately low. A forecast from a linear fit on six points is a
            // direction, not a number, and the card should say so.
            'confidence' => 0.55,
        ]);
    }

    private function resolveDimension(array $p): ?array
    {
        $m = $this->metrics;

        $cfg = match ($p['dim']) {
            'location' => [
                'rows' => $m->properties()['byLocation'],
                'cols' => ['Location', 'Listings', 'Sold', 'Sell-through', 'Avg DOM'],
                'get' => fn ($r) => [$r['key'], Fmt::num($r['listings']), Fmt::num($r['sold']),
                    Fmt::pct($r['sellThrough'], 0), round($r['avgDom']).'d'],
                'val' => fn ($r) => $r['value'],
                'format' => 'money',
            ],
            'source' => [
                'rows' => $m->leads()['bySource'],
                'cols' => ['Source', 'Leads', 'Closed', 'Conversion', 'Pipeline'],
                'get' => fn ($r) => [$r['key'], Fmt::num($r['leads']), Fmt::num($r['won']),
                    Fmt::pct($r['conversion'], 1), Fmt::moneyShort($r['pipeline'])],
                'val' => fn ($r) => $r['leads'],
                'format' => 'count',
            ],
            'agent' => [
                'rows' => $m->agents()['rows'],
                'cols' => ['Agent', 'Revenue', 'Deals', 'Close rate'],
                'get' => fn ($r) => [$r['name'], Fmt::moneyShort($r['revenue']), Fmt::num($r['won']),
                    Fmt::pct($r['closeRate'], 0)],
                'val' => fn ($r) => $r['revenue'],
                'format' => 'money',
            ],
            'type' => [
                'rows' => $m->properties()['byType'],
                'cols' => ['Type', 'Listings', 'Sold', 'Avg price', 'Avg DOM'],
                'get' => fn ($r) => [$r['key'], Fmt::num($r['listings']), Fmt::num($r['sold']),
                    Fmt::moneyShort($r['avgPrice']), round($r['avgDom']).'d'],
                'val' => fn ($r) => $r['listings'],
                'format' => 'count',
            ],
            'segment' => [
                'rows' => $m->customers()['bySegment'],
                'cols' => ['Segment', 'Customers', 'Total LTV', 'Avg LTV'],
                'get' => fn ($r) => [Fmt::titleCase($r['key']), Fmt::num($r['count']),
                    Fmt::moneyShort($r['ltv']), Fmt::moneyShort($r['avgLtv'])],
                'val' => fn ($r) => $r['ltv'],
                'format' => 'money',
            ],
            'month' => [
                'rows' => $m->sales(90)['series'],
                'cols' => ['Month', 'Revenue', 'Deals'],
                'get' => fn ($r) => [$r['label'], Fmt::moneyShort($r['value']), Fmt::num($r['count'])],
                'val' => fn ($r) => $r['value'],
                'format' => 'money',
            ],
            default => null,
        };

        if (! $cfg || ! $cfg['rows']) {
            return null;
        }

        $rows = collect($cfg['rows'])->take(12);

        return $this->answer([
            'kind' => 'ranking',
            'headline' => 'Breakdown by '.$p['dim'],
            'narrative' => $this->b(Fmt::num(count($cfg['rows']))).' '.$p['dim'].'s. Largest is '
                .$this->b((string) $cfg['get']($rows->first())[0]).'.',
            'table' => [
                'columns' => $cfg['cols'],
                'rows' => $rows->map($cfg['get'])->all(),
            ],
            'chart' => ['kind' => 'bar', 'payload' => [
                'data' => $rows->map(fn ($r) => [
                    'label' => (string) $cfg['get']($r)[0],
                    'value' => $cfg['val']($r),
                ])->all(),
                'opts' => ['format' => $cfg['format'], 'height' => 210],
            ]],
        ]);
    }

    /**
     * Everything the engine knows, flattened into one sheet of figures.
     *
     * Handed to the narrator when the router cannot resolve a question. It is
     * FIGURES ONLY — nine metric groups, the platform counters, and the titles
     * of what is currently flagged. No rows, no names of individual customers,
     * nothing the model could reshape into a claim about a specific person.
     *
     * That constraint is what makes the fallback safe to ship. The model's job
     * is to find which of these numbers answers the question and say so in a
     * sentence; it is structurally unable to produce a figure that is not
     * already on this list.
     */
    private function factSheet(): array
    {
        $m = $this->metrics;
        $s = $m->sales(90);
        $l = $m->leads(90);
        $pr = $m->properties();
        $f = $m->finance();
        $mk = $m->marketing();
        $c = $m->customers();
        $fb = $m->feedback();
        $a = $m->agents();

        $top = fn (array $rows, string $key, int $n = 5) => collect($rows)->take($n)
            ->map(fn ($r) => $r['key'].' ('.$r[$key].')')->implode(', ');

        return [
            /* sales */
            'revenue_last_90_days' => $s['revenue'],
            'revenue_change_vs_prior_90' => Fmt::delta($s['revenueChange']),
            'deals_closed_last_90_days' => $s['deals'],
            'average_deal_size' => $s['avgDeal'],
            'win_rate_of_decided_deals' => Fmt::pct($s['winRate'], 1),
            'average_sales_cycle_days' => round($s['cycleDays']),
            'commission_last_90_days' => $s['commission'],

            /* leads */
            'open_leads' => $l['open'],
            'new_leads_last_90_days' => $l['new'],
            'open_pipeline_value' => $l['pipeline'],
            'stale_leads_no_contact_14_days' => $l['stale'],
            'stale_pipeline_value' => $l['stalePipeline'],
            'normal_stale_backlog' => $l['staleBaseline'],
            'hot_leads_score_70_plus' => $l['hot'],
            'lead_to_sale_conversion' => Fmt::pct($l['conversion'], 2),
            'lead_sources_by_volume' => $top($l['bySource'], 'leads'),

            /* properties */
            'properties_available' => $pr['available'],
            'properties_reserved' => $pr['reserved'],
            'properties_sold' => $pr['sold'],
            'inventory_value_available' => $pr['inventoryValue'],
            'average_listing_price' => $pr['avgPrice'],
            'median_days_on_market' => round($pr['domMedian']),
            'stale_listings_over_twice_median_dom' => $pr['stale'],
            'stale_listing_value' => $pr['staleValue'],
            'sell_through_rate' => Fmt::pct($pr['sellThrough'], 1),
            'locations_by_listing_count' => $top($pr['byLocation'], 'listings', 6),
            'property_types_by_listing_count' => $top($pr['byType'], 'listings', 6),

            /* finance */
            'total_collected' => $f['collected'],
            'total_outstanding' => $f['outstanding'],
            'overdue_amount' => $f['overdueAmount'],
            'overdue_instalment_count' => $f['overdueCount'],
            'accounts_at_risk' => $f['atRiskAccounts'],
            'aged_over_90_days' => $f['aging']['90+'] ?? 0,
            'collection_ratio' => Fmt::pct($f['collectionRatio'], 1),
            'falling_due_next_30_days' => $f['expected30'],

            /* marketing */
            'marketing_spend' => $mk['spend'],
            'cost_per_lead' => $mk['cpl'],
            'cost_per_customer_acquired' => $mk['cac'],
            'return_on_ad_spend' => number_format($mk['roas'], 2).'x',
            'campaign_attributed_leads' => $mk['leads'],
            'channels_by_spend' => $top($mk['byChannel'], 'spend', 6),

            /* investors and satisfaction */
            'total_investors' => $c['total'],
            'combined_lifetime_value' => $c['ltv'],
            'average_lifetime_value' => $c['avgLtv'],
            'repeat_buyers' => $c['repeat'],
            'average_satisfaction_out_of_5' => round($c['satisfaction'], 2),
            'promoters' => $c['promoters'],
            'detractors' => $c['detractors'],
            'reviews_last_60_days' => $fb['window'],
            'negative_reviews_last_60_days' => $fb['negative'],
            'top_complaint_themes' => $top($fb['tags'], 'count', 5),

            /* the sales floor */
            'realtor_count' => count($a['rows']),
            'median_close_rate' => Fmt::pct($a['baselineCloseRate'], 1),
            'revenue_share_of_top_three' => Fmt::pct($a['concentration'], 1),

            /* the instalment platform */
            'commitments_awaiting_approval' => Deal::query()->where('status', 'pending')->count(),
            'commitments_live_and_paying' => Deal::query()->live()->count(),
            'commitments_completed' => Deal::query()->where('status', 'completed')->count(),
            'investors_with_no_realtor_attached' => Customer::query()->unassigned()->count(),
            'approved_realtors' => Agent::query()->approved()->count(),
            'realtors_awaiting_approval' => Agent::query()->pending()->count(),
            'instalments_paid' => Payment::whereNotNull('paid_at')->count(),
            'instalments_still_scheduled' => Payment::whereNull('paid_at')->count(),

            /* what is currently flagged */
            'open_findings' => count($this->engine->live()),
            'open_finding_titles' => collect($this->engine->live())->take(8)
                ->map(fn ($i) => $i->title)->implode(' | ') ?: 'none',
        ];
    }

    /**
     * The router could not place this question. Try the model before giving up.
     *
     * Order matters: the router is tried FIRST and always wins, because a
     * routed answer comes with a chart, a table and a drill-through to the
     * rows behind it, and this one is prose. The model is the fallback, not
     * the front door.
     */
    private function resolveUnknown(array $p): array
    {
        $written = $this->narrator->answer($p['raw'], $this->factSheet());

        if ($written !== null) {
            return $this->answer([
                'kind' => 'ai',
                'headline' => 'From your figures',
                'narrative' => $written,
                // Lower than a routed answer on purpose. This one was phrased
                // from a fact sheet rather than computed against a named
                // metric, and the card should not claim otherwise.
                'confidence' => 0.7,
                'drill' => ['label' => 'Open all findings', 'route' => route('app.insights')],
            ]);
        }

        return $this->cannotAnswer($p);
    }

    /** The honest card. Reached when nothing — router or model — could answer. */
    private function cannotAnswer(array $p): array
    {
        $guesses = [];

        if (preg_match('/lead|contact|follow/i', $p['raw'])) {
            $guesses[] = 'How many leads have gone stale?';
        }
        if (preg_match('/money|cash|pay|owe/i', $p['raw'])) {
            $guesses[] = 'How much is overdue?';
        }
        if (preg_match('/sell|sale|revenue/i', $p['raw'])) {
            $guesses[] = 'What is our revenue this quarter?';
        }
        if (! $guesses) {
            $guesses = ['What should I worry about?', 'Which lead sources convert best?', 'How much is overdue?'];
        }

        return $this->answer([
            'kind' => 'unknown',
            'headline' => 'That data is not available',
            'narrative' => 'I could not answer that from what this business actually records. '
                .'Rather than guess at a number — which is the one mistake that would make every '
                .'other figure here worth doubting — I would rather say so. These are close to '
                .'what you asked and I can answer them.',
            'items' => array_map(fn ($g) => ['title' => $g, 'ask' => $g], $guesses),
            'confidence' => 0.0,
        ]);
    }

    private function metricToArea(?string $metric): ?string
    {
        return [
            'revenue' => 'sales', 'deals' => 'sales', 'conversion' => 'sales', 'agents' => 'sales',
            'leads' => 'leads', 'pipeline' => 'leads', 'stale' => 'leads',
            'inventory' => 'properties', 'dom' => 'properties',
            'overdue' => 'finance', 'collections' => 'finance',
            'spend' => 'marketing', 'roas' => 'marketing', 'cac' => 'marketing', 'cpl' => 'marketing',
            'satisfaction' => 'feedback', 'complaints' => 'feedback',
            'customers' => 'customers',
        ][$metric] ?? null;
    }

    /** Suggested questions — guarantee the path, and teach the grammar. */
    public function suggestions(): array
    {
        $h = $this->metrics->headline();
        $l = $this->metrics->leads();

        $base = [
            'What should I worry about?',
            'What should I do next?',
            'Why is our conversion falling?',
            'Which lead sources convert best?',
            'Show me the worst campaigns by ROAS',
            'How much is overdue?',
            'Top 5 agents by revenue',
            'Revenue by location',
            'What is our pipeline?',
            'Forecast next month',
        ];

        if ($l['stale'] > 10) {
            array_unshift($base, "Why do we have {$l['stale']} stale leads?");
        }
        if ($h['overdue'] > 0) {
            $base[] = 'Which customers are late on payments?';
        }

        // The two queues, surfaced only while they actually have something in
        // them. A suggested question that returns "none" teaches nothing.
        if (Deal::query()->where('status', 'pending')->exists()) {
            array_unshift($base, 'How many commitments are waiting for approval?');
        }
        if (Customer::query()->unassigned()->exists()) {
            $base[] = 'How many investors have no realtor attached?';
        }
        if (Agent::query()->pending()->exists()) {
            $base[] = 'How many realtors are waiting on approval?';
        }

        return $base;
    }
}
