<?php

namespace App\Engine;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Layer 2 — Metrics.
 *
 * In the browser prototype these were array loops over three thousand records.
 * Here they are SQL aggregates, because you cannot load a tenant's leads into
 * PHP memory on every page view. The split that works:
 *
 *     SQL  -> grouping and aggregation, returning a small series
 *     PHP  -> the statistics over it (slope, z-score, smoothing)
 *
 * Results are cached per tenant and flushed wholesale when an action mutates
 * anything. Cache granularity finer than "this tenant" is a bug farm: every
 * detector reads several metrics and the invalidation graph between them is
 * not worth maintaining for a payload this small.
 */
class Metrics
{
    private array $memo = [];

    public function __construct(private Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function flush(): void
    {
        $this->memo = [];
        foreach (Cache::get($this->key('index'), []) as $k) {
            Cache::forget($k);
        }
        Cache::forget($this->key('index'));
    }

    private function key(string $suffix): string
    {
        return "nexabi:metrics:{$this->tenant->id}:{$suffix}";
    }

    /**
     * Request-local memo in front of the cache store.
     *
     * Keys are tracked in an index so flush() can clear them without cache
     * tags — the database cache driver does not support tagging.
     */
    private function remember(string $name, \Closure $fn): array
    {
        if (isset($this->memo[$name])) {
            return $this->memo[$name];
        }

        $key = $this->key($name);
        $index = Cache::get($this->key('index'), []);
        if (! in_array($key, $index, true)) {
            $index[] = $key;
            Cache::put($this->key('index'), $index, now()->addDay());
        }

        return $this->memo[$name] = Cache::remember($key, now()->addMinutes(10), $fn);
    }

    private function t(): int
    {
        return $this->tenant->id;
    }

    /* ============================================================= SALES */

    public function sales(int $days = 90): array
    {
        return $this->remember("sales:$days", function () use ($days) {
            $from = now()->subDays($days);
            $priorFrom = now()->subDays($days * 2);

            $won = fn ($a, $b) => DB::table('deals')
                ->where('tenant_id', $this->t())->where('won', true)
                ->whereBetween('closed_at', [$a, $b]);

            $cur = $won($from, now())->selectRaw(
                'COUNT(*) c, COALESCE(SUM(value),0) v, COALESCE(SUM(commission),0) k, '
                .'COALESCE(AVG(NULLIF(cycle_days,0)),0) cyc'
            )->first();

            $prev = $won($priorFrom, $from)
                ->selectRaw('COUNT(*) c, COALESCE(SUM(value),0) v')->first();

            // Win rate is of DECIDED deals — leads still in flight are excluded,
            // so this is not lead-to-sale conversion, and it is labelled so.
            $decided = fn ($a, $b) => (int) DB::table('deals')
                ->where('tenant_id', $this->t())
                ->whereBetween('closed_at', [$a, $b])->count();

            $curDecided = $decided($from, now());
            $prevDecided = $decided($priorFrom, $from);
            $winRate = Stats::safeDiv((float) $cur->c, (float) $curDecided);

            return [
                'revenue' => (float) $cur->v,
                'revenueChange' => Stats::change((float) $cur->v, (float) $prev->v),
                'deals' => (int) $cur->c,
                'dealsChange' => Stats::change((float) $cur->c, (float) $prev->c),
                'avgDeal' => $cur->c ? (float) $cur->v / $cur->c : 0.0,
                'commission' => (float) $cur->k,
                'cycleDays' => (float) $cur->cyc,
                'winRate' => $winRate,
                'winRateChange' => $winRate - Stats::safeDiv((float) $prev->c, (float) $prevDecided),
                'series' => $this->monthly('deals', 'closed_at', 'SUM(value)', ['won' => true]),
                'countSeries' => $this->monthly('deals', 'closed_at', 'COUNT(*)', ['won' => true]),
            ];
        });
    }

    /* ============================================================ FUNNEL */

    public function funnel(int $days = 180): array
    {
        return $this->remember("funnel:$days", function () use ($days) {
            $order = ['new', 'contacted', 'qualified', 'viewing', 'negotiating', 'won'];

            /**
             * Count on the deepest stage ever reached, not current status. Lost
             * leads still passed through the stages they passed through, and
             * dropping them flatters every conversion figure in the product.
             */
            $rows = DB::table('leads')
                ->where('tenant_id', $this->t())
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('max_stage, COUNT(*) c')
                ->groupBy('max_stage')
                ->pluck('c', 'max_stage')->all();

            $rank = array_flip($order);
            $reached = [];
            foreach ($order as $i => $stage) {
                $n = 0;
                foreach ($rows as $ms => $c) {
                    if (isset($rank[$ms]) && $rank[$ms] >= $i) {
                        $n += (int) $c;
                    }
                }
                $reached[$i] = $n;
            }

            $stages = [];
            foreach ($order as $i => $stage) {
                $stages[] = [
                    'stage' => $stage,
                    'count' => $reached[$i],
                    'conversion' => $i === 0 ? 1.0 : Stats::safeDiv($reached[$i], $reached[$i - 1]),
                    'dropped' => $i === 0 ? 0 : $reached[$i - 1] - $reached[$i],
                ];
            }

            return [
                'stages' => $stages,
                'weeklyStepConversion' => $this->weeklyStepConversion(),
            ];
        });
    }

    /**
     * Weekly viewing-to-negotiating conversion, by acquisition week.
     *
     * COHORT MATURATION: leads captured in the last few weeks have not had time
     * to progress, so their conversion is near zero for reasons that have
     * nothing to do with performance. Including them manufactures a decline in
     * every dataset, so the most recent weeks are dropped.
     */
    private function weeklyStepConversion(int $weeksBack = 18, int $maturityWeeks = 3): array
    {
        $rows = DB::table('leads')
            ->where('tenant_id', $this->t())
            ->where('created_at', '>=', now()->subWeeks($weeksBack)->startOfWeek())
            ->selectRaw('YEARWEEK(created_at, 3) wk, max_stage, COUNT(*) c')
            ->groupBy('wk', 'max_stage')->get();

        $rank = array_flip(['new', 'contacted', 'qualified', 'viewing', 'negotiating', 'won']);
        $viewed = [];
        $advanced = [];

        foreach ($rows as $r) {
            $ms = $rank[$r->max_stage] ?? -1;
            $viewed[$r->wk] = ($viewed[$r->wk] ?? 0) + ($ms >= 3 ? (int) $r->c : 0);
            $advanced[$r->wk] = ($advanced[$r->wk] ?? 0) + ($ms >= 4 ? (int) $r->c : 0);
        }

        ksort($viewed);
        $weeks = array_keys($viewed);
        $mature = array_slice($weeks, 0, max(0, count($weeks) - $maturityWeeks));

        $out = [];
        foreach ($mature as $wk) {
            $out[] = [
                'week' => $wk,
                'n' => $viewed[$wk],
                'value' => Stats::safeDiv($advanced[$wk] ?? 0, $viewed[$wk]),
            ];
        }

        return $out;
    }

    /* ============================================================= LEADS */

    public function leads(int $days = 90): array
    {
        return $this->remember("leads:$days", function () use ($days) {
            $base = fn () => DB::table('leads')->where('tenant_id', $this->t());

            $openCount = (int) (clone $base())->where('is_open', true)->count();
            $pipeline = (float) (clone $base())->where('is_open', true)->sum('budget');

            $staleQ = fn () => (clone $base())->where('is_open', true)
                ->where('last_contact_at', '<=', now()->subDays(14));

            $stale = (int) $staleQ()->count();
            $stalePipeline = (float) $staleQ()->sum('budget');
            $staleAvgScore = (float) ($staleQ()->avg('score') ?? 0);
            $oldest = $staleQ()->min('last_contact_at');

            $new = (int) (clone $base())->where('created_at', '>=', now()->subDays($days))->count();
            $prevNew = (int) (clone $base())->whereBetween('created_at',
                [now()->subDays($days * 2), now()->subDays($days)])->count();

            $bySource = (clone $base())
                ->selectRaw("source, COUNT(*) leads,
                    SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) won,
                    AVG(score) avg_score,
                    SUM(CASE WHEN is_open=1 THEN budget ELSE 0 END) pipeline")
                ->groupBy('source')->orderByDesc('leads')->get()
                ->map(fn ($r) => [
                    'key' => $r->source,
                    'leads' => (int) $r->leads,
                    'won' => (int) $r->won,
                    'conversion' => Stats::safeDiv((int) $r->won, (int) $r->leads),
                    'avgScore' => (float) $r->avg_score,
                    'pipeline' => (float) $r->pipeline,
                ])->all();

            $total = (int) (clone $base())->count();
            $wonTotal = (int) (clone $base())->where('status', 'won')->count();

            return [
                'total' => $total,
                'open' => $openCount,
                'new' => $new,
                'newChange' => Stats::change($new, $prevNew),
                'stale' => $stale,
                'stalePipeline' => $stalePipeline,
                'staleAvgScore' => $staleAvgScore,
                'staleOldestDays' => $oldest ? (int) Carbon::parse($oldest)->diffInDays(now()) : 0,
                // A healthy backlog is about 6% of the open book.
                'staleBaseline' => max(1, (int) round($openCount * 0.06)),
                'pipeline' => $pipeline,
                'avgScore' => (float) ((clone $base())->where('is_open', true)->avg('score') ?? 0),
                'hot' => (int) (clone $base())->where('is_open', true)->where('score', '>=', 70)->count(),
                'conversion' => Stats::safeDiv($wonTotal, $total),
                'bySource' => $bySource,
                'byStatus' => (clone $base())->selectRaw('status, COUNT(*) c')
                    ->groupBy('status')->pluck('c', 'status')->all(),
                'series' => $this->monthly('leads', 'created_at', 'COUNT(*)'),
            ];
        });
    }

    /* ======================================================== PROPERTIES */

    public function properties(): array
    {
        return $this->remember('properties', function () {
            $base = fn () => DB::table('properties')->where('tenant_id', $this->t());

            $available = (int) (clone $base())->where('status', 'available')->count();
            $sold = (int) (clone $base())->where('status', 'sold')->count();
            $total = (int) (clone $base())->count();

            $domMedian = Stats::median(
                (clone $base())->where('status', 'sold')->pluck('days_on_market')
                    ->map(fn ($v) => (float) $v)->all()
            );

            /**
             * Demand index = inquiries per AVAILABLE unit in a segment. MySQL
             * has no FILTER clause, so the conditional count is a SUM(CASE ...)
             * — same result, uglier syntax.
             */
            $segments = (clone $base())
                ->selectRaw("location, type, bedrooms, COUNT(*) listings,
                    SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) available,
                    SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) sold,
                    SUM(inquiries) inquiries, AVG(price) avg_price,
                    AVG(CASE WHEN status='sold' THEN days_on_market END) avg_dom")
                ->groupBy('location', 'type', 'bedrooms')
                ->havingRaw('COUNT(*) >= 3')->get()
                ->map(fn ($r) => [
                    'key' => $r->location.'|'.$r->type.'|'.$r->bedrooms,
                    'location' => $r->location, 'type' => $r->type, 'bedrooms' => (int) $r->bedrooms,
                    'listings' => (int) $r->listings, 'available' => (int) $r->available,
                    'sold' => (int) $r->sold, 'inquiries' => (int) $r->inquiries,
                    'demandIndex' => Stats::safeDiv((int) $r->inquiries, max(1, (int) $r->available)),
                    'avgPrice' => (float) $r->avg_price, 'avgDom' => (float) ($r->avg_dom ?? 0),
                ])->all();

            // Baseline drawn only from segments that actually hold stock. A
            // segment with zero available units has an undefined demand index,
            // not an infinite one, and letting those in inflates the norm.
            $stocked = array_values(array_filter($segments, fn ($s) => $s['available'] >= 2));
            $baselineDemand = Stats::median(
                array_column(count($stocked) >= 5 ? $stocked : $segments, 'demandIndex')
            );

            usort($segments, fn ($a, $b) => $b['demandIndex'] <=> $a['demandIndex']);

            $byLocation = (clone $base())
                ->selectRaw("location, COUNT(*) listings,
                    SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) available,
                    SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) sold,
                    SUM(price) value, SUM(inquiries) inquiries,
                    AVG(CASE WHEN status='sold' THEN days_on_market END) avg_dom")
                ->groupBy('location')->orderByDesc('listings')->get()
                ->map(fn ($r) => [
                    'key' => $r->location, 'listings' => (int) $r->listings,
                    'available' => (int) $r->available, 'sold' => (int) $r->sold,
                    'value' => (float) $r->value, 'inquiries' => (int) $r->inquiries,
                    'avgDom' => (float) ($r->avg_dom ?? 0),
                    'sellThrough' => Stats::safeDiv((int) $r->sold, (int) $r->listings),
                ])->all();

            $byType = (clone $base())
                ->selectRaw("type, COUNT(*) listings,
                    SUM(CASE WHEN status='sold' THEN 1 ELSE 0 END) sold,
                    AVG(price) avg_price,
                    AVG(CASE WHEN status='sold' THEN days_on_market END) avg_dom")
                ->groupBy('type')->orderByDesc('listings')->get()
                ->map(fn ($r) => [
                    'key' => $r->type, 'listings' => (int) $r->listings, 'sold' => (int) $r->sold,
                    'avgPrice' => (float) $r->avg_price, 'avgDom' => (float) ($r->avg_dom ?? 0),
                    'sellThrough' => Stats::safeDiv((int) $r->sold, (int) $r->listings),
                ])->all();

            $staleCut = $domMedian * 2.2;
            $staleQ = fn () => (clone $base())->where('status', 'available')
                ->where('days_on_market', '>', $staleCut);

            return [
                'total' => $total, 'available' => $available,
                'reserved' => (int) (clone $base())->where('status', 'reserved')->count(),
                'sold' => $sold,
                'inventoryValue' => (float) (clone $base())->where('status', 'available')->sum('price'),
                'avgPrice' => (float) ((clone $base())->where('status', 'available')->avg('price') ?? 0),
                'avgDom' => (float) ((clone $base())->where('status', 'sold')->avg('days_on_market') ?? 0),
                'domMedian' => $domMedian, 'staleCut' => $staleCut,
                'stale' => (int) $staleQ()->count(),
                'staleValue' => (float) $staleQ()->sum('price'),
                'totalInquiries' => (int) (clone $base())->sum('inquiries'),
                'segments' => $segments, 'baselineDemand' => $baselineDemand,
                'byLocation' => $byLocation, 'byType' => $byType,
                'sellThrough' => Stats::safeDiv($sold, $total),
            ];
        });
    }

    /* =========================================================== FINANCE */

    public function finance(): array
    {
        return $this->remember('finance', function () {
            $base = fn () => DB::table('payments')->where('tenant_id', $this->t());

            $collected = (float) (clone $base())->whereNotNull('paid_at')->sum('amount');
            $due = (float) (clone $base())->where('due_at', '<=', now())->sum('amount');

            $unpaidDue = fn () => (clone $base())->whereNull('paid_at')->where('due_at', '<=', now());
            $overdueQ = fn () => (clone $base())->whereNull('paid_at')
                ->where('due_at', '<', now()->subDays(30));

            $aging = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
            foreach ($unpaidDue()->selectRaw(
                "CASE WHEN DATEDIFF(NOW(), due_at) <= 30 THEN '0-30'
                      WHEN DATEDIFF(NOW(), due_at) <= 60 THEN '31-60'
                      WHEN DATEDIFF(NOW(), due_at) <= 90 THEN '61-90'
                      ELSE '90+' END bucket, SUM(amount) total"
            )->groupBy('bucket')->get() as $r) {
                $aging[$r->bucket] = (float) $r->total;
            }

            $byCustomer = DB::table('payments as p')
                ->join('customers as c', 'c.id', '=', 'p.customer_id')
                ->where('p.tenant_id', $this->t())
                ->whereNull('p.paid_at')
                ->where('p.due_at', '<', now()->subDays(30))
                ->selectRaw('c.id, c.name, c.segment, c.rank, SUM(p.amount) amount, '
                    .'COUNT(*) cnt, MAX(DATEDIFF(NOW(), p.due_at)) max_overdue')
                ->groupBy('c.id', 'c.name', 'c.segment', 'c.rank')
                ->orderByDesc('amount')->get()
                ->map(fn ($r) => [
                    'id' => (int) $r->id, 'name' => $r->name, 'segment' => $r->segment,
                    'rank' => (int) $r->rank, 'amount' => (float) $r->amount,
                    'count' => (int) $r->cnt, 'maxOverdue' => (int) $r->max_overdue,
                ])->all();

            return [
                'collected' => $collected,
                'collectedSeries' => $this->monthly('payments', 'paid_at', 'SUM(amount)'),
                'outstanding' => (float) $unpaidDue()->sum('amount'),
                'overdueAmount' => (float) $overdueQ()->sum('amount'),
                'overdueCount' => (int) $overdueQ()->count(),
                'collectionRatio' => Stats::safeDiv($collected, $due),
                'aging' => $aging,
                'byCustomer' => $byCustomer,
                'atRiskAccounts' => count($byCustomer),
                'expected30' => (float) (clone $base())->whereNull('paid_at')
                    ->whereBetween('due_at', [now(), now()->addDays(30)])->sum('amount'),
            ];
        });
    }

    /* ========================================================= MARKETING */

    public function marketing(): array
    {
        return $this->remember('marketing', function () {
            // Leads and won deals counted separately then joined in PHP: a
            // single query with two LEFT JOINs fans out and double-counts
            // revenue, which is the classic way to make a ROAS figure lie.
            $campaigns = DB::table('campaigns')->where('tenant_id', $this->t())->get();

            $leadCounts = DB::table('leads')->where('tenant_id', $this->t())
                ->whereNotNull('campaign_id')
                ->selectRaw('campaign_id, COUNT(*) c')
                ->groupBy('campaign_id')->pluck('c', 'campaign_id')->all();

            $dealAgg = DB::table('deals')->where('tenant_id', $this->t())
                ->where('won', true)->whereNotNull('campaign_id')
                ->selectRaw('campaign_id, COUNT(*) c, SUM(value) v')
                ->groupBy('campaign_id')->get()->keyBy('campaign_id');

            $rows = $campaigns->map(function ($c) use ($leadCounts, $dealAgg) {
                $leads = (int) ($leadCounts[$c->id] ?? 0);
                $won = (int) ($dealAgg[$c->id]->c ?? 0);
                $revenue = (float) ($dealAgg[$c->id]->v ?? 0);
                $spend = (float) $c->spend;

                return [
                    'id' => (int) $c->id, 'name' => $c->name, 'channel' => $c->channel,
                    'source' => $c->source, 'status' => $c->status, 'spend' => $spend,
                    'impressions' => (int) $c->impressions, 'clicks' => (int) $c->clicks,
                    'startAt' => $c->start_at, 'leads' => $leads, 'won' => $won,
                    'revenue' => $revenue,
                    'cpl' => Stats::safeDiv($spend, $leads),
                    'cac' => Stats::safeDiv($spend, $won),
                    'conversion' => Stats::safeDiv($won, $leads),
                    'roas' => Stats::safeDiv($revenue, $spend),
                    'ctr' => Stats::safeDiv((int) $c->clicks, (int) $c->impressions),
                ];
            })->sortByDesc('spend')->values()->all();

            $spend = array_sum(array_column($rows, 'spend'));
            $leads = array_sum(array_column($rows, 'leads'));
            $won = array_sum(array_column($rows, 'won'));
            $revenue = array_sum(array_column($rows, 'revenue'));
            $qualified = array_values(array_filter($rows, fn ($c) => $c['leads'] >= 10));

            $byChannel = [];
            foreach ($rows as $c) {
                $k = $c['channel'];
                $byChannel[$k] ??= ['key' => $k, 'spend' => 0.0, 'leads' => 0, 'won' => 0, 'revenue' => 0.0];
                $byChannel[$k]['spend'] += $c['spend'];
                $byChannel[$k]['leads'] += $c['leads'];
                $byChannel[$k]['won'] += $c['won'];
                $byChannel[$k]['revenue'] += $c['revenue'];
            }
            foreach ($byChannel as $k => $v) {
                $byChannel[$k]['cpl'] = Stats::safeDiv($v['spend'], $v['leads']);
                $byChannel[$k]['conversion'] = Stats::safeDiv($v['won'], $v['leads']);
                $byChannel[$k]['roas'] = Stats::safeDiv($v['revenue'], $v['spend']);
            }
            $byChannel = array_values($byChannel);
            usort($byChannel, fn ($a, $b) => $b['spend'] <=> $a['spend']);

            return [
                'spend' => $spend, 'leads' => $leads, 'won' => $won, 'revenue' => $revenue,
                'cpl' => Stats::safeDiv($spend, $leads),
                'cac' => Stats::safeDiv($spend, $won),
                'roas' => Stats::safeDiv($revenue, $spend),
                'conversion' => Stats::safeDiv($won, $leads),
                'baselineConversion' => Stats::median(array_column($qualified, 'conversion')),
                'baselineCpl' => Stats::median(array_column($qualified, 'cpl')),
                'campaigns' => $rows,
                'active' => array_values(array_filter($rows, fn ($c) => $c['status'] === 'active')),
                'byChannel' => $byChannel,
            ];
        });
    }

    /* ========================================================= CUSTOMERS */

    public function customers(): array
    {
        return $this->remember('customers', function () {
            $base = fn () => DB::table('customers')->where('tenant_id', $this->t());

            $repeat = DB::table('deals')->where('tenant_id', $this->t())
                ->where('won', true)->whereNotNull('customer_id')
                ->selectRaw('customer_id')->groupBy('customer_id')
                ->havingRaw('COUNT(*) > 1')->get()->count();

            return [
                'total' => (int) (clone $base())->count(),
                'ltv' => (float) (clone $base())->sum('ltv'),
                'avgLtv' => (float) ((clone $base())->avg('ltv') ?? 0),
                'repeat' => $repeat,
                'satisfaction' => (float) ((clone $base())->whereNotNull('satisfaction')
                    ->avg('satisfaction') ?? 0),
                'detractors' => (int) (clone $base())->whereNotNull('satisfaction')
                    ->where('satisfaction', '<=', 2.5)->count(),
                'promoters' => (int) (clone $base())->whereNotNull('satisfaction')
                    ->where('satisfaction', '>=', 4.5)->count(),
                'bySegment' => (clone $base())
                    ->selectRaw('segment, COUNT(*) c, SUM(ltv) ltv, AVG(ltv) avg_ltv, AVG(satisfaction) sat')
                    ->groupBy('segment')->get()
                    ->map(fn ($r) => [
                        'key' => $r->segment, 'count' => (int) $r->c, 'ltv' => (float) $r->ltv,
                        'avgLtv' => (float) $r->avg_ltv, 'satisfaction' => (float) ($r->sat ?? 0),
                    ])->all(),
                'newSeries' => $this->monthly('customers', 'since', 'COUNT(*)'),
            ];
        });
    }

    /* ========================================================== FEEDBACK */

    public function feedback(int $windowDays = 60): array
    {
        return $this->remember("feedback:$windowDays", function () use ($windowDays) {
            $from = now()->subDays($windowDays);
            $priorFrom = now()->subDays($windowDays * 2);

            $cur = DB::table('feedbacks')->where('tenant_id', $this->t())
                ->whereBetween('created_at', [$from, now()])->get();
            $prev = DB::table('feedbacks')->where('tenant_id', $this->t())
                ->whereBetween('created_at', [$priorFrom, $from])->get();

            $tally = function ($rows) {
                $out = [];
                foreach ($rows as $r) {
                    foreach (json_decode($r->tags ?: '[]', true) ?: [] as $tag) {
                        $out[$tag] ??= ['count' => 0, 'negative' => 0, 'ratingSum' => 0];
                        $out[$tag]['count']++;
                        $out[$tag]['ratingSum'] += (int) $r->rating;
                        if ($r->rating <= 2) {
                            $out[$tag]['negative']++;
                        }
                    }
                }

                return $out;
            };

            $curTags = $tally($cur);
            $prevTags = $tally($prev);

            $tags = [];
            foreach (array_unique(array_merge(array_keys($curTags), array_keys($prevTags))) as $tag) {
                $c = $curTags[$tag]['count'] ?? 0;
                $p = $prevTags[$tag]['count'] ?? 0;
                $tags[] = [
                    'key' => $tag, 'count' => $c, 'prev' => $p,
                    'change' => Stats::change($c, $p),
                    'negative' => $curTags[$tag]['negative'] ?? 0,
                    'avgRating' => $c ? $curTags[$tag]['ratingSum'] / $c : 0.0,
                ];
            }
            usort($tags, fn ($a, $b) => $b['count'] <=> $a['count']);

            $avg = fn ($rows) => $rows->count()
                ? $rows->sum(fn ($r) => (int) $r->rating) / $rows->count() : 0.0;
            $negative = $cur->filter(fn ($r) => $r->rating <= 2)->count();

            return [
                'total' => (int) DB::table('feedbacks')->where('tenant_id', $this->t())->count(),
                'window' => $cur->count(),
                'avgRating' => $avg($cur),
                'prevRating' => $avg($prev),
                'ratingChange' => $avg($cur) - $avg($prev),
                'negative' => $negative,
                'negativeRate' => Stats::safeDiv($negative, $cur->count()),
                'tags' => $tags,
                'distribution' => collect([1, 2, 3, 4, 5])->map(fn ($r) => [
                    'rating' => $r,
                    'count' => $cur->filter(fn ($x) => (int) $x->rating === $r)->count(),
                ])->all(),
            ];
        });
    }

    /* ============================================================ AGENTS */

    public function agents(int $days = 180): array
    {
        return $this->remember("agents:$days", function () {
            $rows = DB::table('agents as a')
                ->where('a.tenant_id', $this->t())
                ->leftJoin('leads as l', 'l.assigned_agent_id', '=', 'a.id')
                ->selectRaw("a.id, a.name, a.role, a.team, a.tier, a.skill, a.active, a.coaching,
                    COUNT(l.id) leads,
                    SUM(CASE WHEN l.is_open=1 THEN 1 ELSE 0 END) open_leads,
                    SUM(CASE WHEN l.status='won' THEN 1 ELSE 0 END) won,
                    SUM(CASE WHEN l.status IN ('won','lost') THEN 1 ELSE 0 END) closed,
                    SUM(CASE WHEN l.is_open=1 AND l.last_contact_at <= ? THEN 1 ELSE 0 END) stale,
                    SUM(CASE WHEN l.is_open=1 THEN l.budget ELSE 0 END) pipeline",
                    [now()->subDays(14)])
                ->groupBy('a.id', 'a.name', 'a.role', 'a.team', 'a.tier', 'a.skill', 'a.active', 'a.coaching')
                ->get();

            $revenue = DB::table('deals')->where('tenant_id', $this->t())->where('won', true)
                ->selectRaw('agent_id, SUM(value) v, SUM(commission) k, COUNT(*) c, AVG(NULLIF(cycle_days,0)) cyc')
                ->groupBy('agent_id')->get()->keyBy('agent_id');

            $agents = $rows->map(function ($r) use ($revenue) {
                $rev = $revenue[$r->id] ?? null;

                return [
                    'id' => (int) $r->id, 'name' => $r->name, 'role' => $r->role,
                    'team' => $r->team, 'tier' => $r->tier, 'skill' => (float) $r->skill,
                    'active' => (bool) $r->active, 'coaching' => (bool) $r->coaching,
                    'leads' => (int) $r->leads, 'openLeads' => (int) $r->open_leads,
                    'won' => (int) $r->won, 'stale' => (int) $r->stale,
                    'closeRate' => Stats::safeDiv((int) $r->won, (int) $r->closed),
                    'pipeline' => (float) $r->pipeline,
                    'revenue' => (float) ($rev->v ?? 0),
                    'commission' => (float) ($rev->k ?? 0),
                    'deals' => (int) ($rev->c ?? 0),
                    'avgCycle' => (float) ($rev->cyc ?? 0),
                ];
            })->sortByDesc('revenue')->values()->all();

            $qualified = array_values(array_filter($agents, fn ($a) => $a['leads'] >= 20));
            $totalRevenue = array_sum(array_column($agents, 'revenue'));
            $top3 = array_sum(array_column(array_slice($agents, 0, 3), 'revenue'));

            return [
                'rows' => $agents,
                'baselineCloseRate' => Stats::median(array_column($qualified, 'closeRate')),
                'totalRevenue' => $totalRevenue,
                'concentration' => Stats::safeDiv($top3, $totalRevenue),
            ];
        });
    }

    /* ========================================================== HEADLINE */

    public function headline(): array
    {
        $s = $this->sales(90);
        $l = $this->leads(90);
        $p = $this->properties();
        $f = $this->finance();
        $m = $this->marketing();

        return [
            'revenue' => $s['revenue'], 'revenueChange' => $s['revenueChange'],
            'deals' => $s['deals'], 'dealsChange' => $s['dealsChange'],
            'winRate' => $s['winRate'], 'winRateChange' => $s['winRateChange'],
            'pipeline' => $l['pipeline'], 'openLeads' => $l['open'], 'staleLeads' => $l['stale'],
            'inventoryValue' => $p['inventoryValue'], 'available' => $p['available'],
            'domMedian' => $p['domMedian'],
            'overdue' => $f['overdueAmount'], 'collectionRatio' => $f['collectionRatio'],
            'roas' => $m['roas'], 'cac' => $m['cac'],
        ];
    }

    /* =========================================================== HELPERS */

    /** Monthly bucket series. The final bucket is flagged incomplete. */
    private function monthly(
        string $table, string $column, string $agg,
        array $where = [], int $monthsBack = 18,
    ): array {
        $q = DB::table($table)->where('tenant_id', $this->t())
            ->whereNotNull($column)
            ->where($column, '>=', now()->subMonths($monthsBack)->startOfMonth())
            ->where($column, '<=', now());

        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        $rows = $q->selectRaw("DATE_FORMAT($column, '%Y-%m-01') bucket, $agg AS value, COUNT(*) cnt")
            ->groupBy('bucket')->orderBy('bucket')->get()->keyBy('bucket');

        $out = [];
        $cursor = now()->subMonths($monthsBack)->startOfMonth();
        $end = now()->startOfMonth();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-01');
            $row = $rows[$key] ?? null;
            $out[] = [
                'label' => $cursor->format('M y'),
                'value' => (float) ($row->value ?? 0),
                'count' => (int) ($row->cnt ?? 0),
                // The month we are standing in is incomplete by definition.
                // Charts render it dashed so nobody reads the dip as a fall.
                'partial' => $cursor->equalTo($end),
            ];
            $cursor->addMonth();
        }

        return $out;
    }
}
