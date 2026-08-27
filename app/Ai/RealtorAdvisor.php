<?php

namespace App\Ai;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Property;
use App\Support\Fmt;
use Illuminate\Support\Facades\DB;

/**
 * Ask NexABI — the realtor's side.
 *
 * A realtor is running a small business inside a bigger one, and the questions
 * that actually make them money are all about where to point their effort:
 * which areas buyers are asking for, where their own conversion is strongest,
 * what stock is moving, what they are owed.
 *
 * Two rules shape everything here.
 *
 * DEMAND IS MARKET-WIDE, PERFORMANCE IS THEIRS. A realtor should see what the
 * whole book of leads is asking for — that is the market signal they are
 * paying attention for — but never another realtor's numbers. So the demand
 * questions aggregate across the tenant and the performance questions filter
 * to `assigned_agent_id`, and the two never mix in one row.
 *
 * NO NAMES THAT ARE NOT THEIRS. Aggregates only on the market side. "Lekki is
 * hot" is a market fact; "Chidi has nine Lekki buyers" is somebody else's book.
 */
class RealtorAdvisor
{
    public function __construct(
        private Agent $realtor,
        private ?NarratorContract $narrator = null,
    ) {
        $this->narrator ??= app(NarratorContract::class);
    }

    public function ask(string $question): array
    {
        $q = mb_strtolower(trim($question));

        $answer = match (true) {
            $this->isAbout($q, 'where.*(focus|market|advertis|spend|push|target)|which (area|location).*(focus|market|advertis|target)|marketing')
                => $this->whereToFocus(),

            $this->isAbout($q, 'interest|demand|want|looking for|asking for|hot|popular|trend')
                => $this->whatBuyersWant(),

            // "my own leads" is the natural phrasing and `my leads` misses it.
            $this->isAbout($q, 'my (own )?(leads?|book|buyers?|clients?|investors?|conversion|close rate)|how (am i|many).*(lead|buyer|client)|my performance|how am i doing')
                => $this->myBook(),

            $this->isAbout($q, 'commission|owed|earn|paid|payout|money')
                => $this->myMoney(),

            $this->isAbout($q, 'stock|inventory|what.*(sell|available)|which propert|units?')
                => $this->whatIsMoving(),

            $this->isAbout($q, 'budget|afford|price range|what can.*buy')
                => $this->budgetPicture(),

            default => null,
        };

        return $answer ?? $this->openQuestion($question);
    }

    private function isAbout(string $q, string $pattern): bool
    {
        return (bool) preg_match('/'.$pattern.'/i', $q);
    }

    /* -------------------------------------------- where to point effort */

    /**
     * The question this whole surface exists for.
     *
     * Demand alone is the wrong answer — an area everybody wants is also an
     * area every realtor is already working. What matters is demand measured
     * against the stock available to sell into it, because that is where a
     * lead converts instead of queueing.
     */
    private function whereToFocus(): array
    {
        $wanted = Lead::query()
            ->whereNotNull('wants_location')
            ->where('created_at', '>=', now()->subDays(120))
            ->selectRaw('wants_location loc, COUNT(*) c, AVG(budget) avg_budget,
                SUM(CASE WHEN is_open = 1 THEN 1 ELSE 0 END) open')
            ->groupBy('loc')->get()->keyBy('loc');

        $stock = Property::query()->onMarket()
            ->selectRaw('location, COUNT(*) c, MIN(price) lo')
            ->groupBy('location')->get()->keyBy('location');

        if ($wanted->isEmpty()) {
            return $this->nothing();
        }

        $rows = $wanted->map(function ($w, $loc) use ($stock) {
            $available = (int) ($stock[$loc]->c ?? 0);

            return [
                'location' => $loc,
                'demand' => (int) $w->c,
                'open' => (int) $w->open,
                'available' => $available,
                'budget' => (float) $w->avg_budget,
                'from' => $available ? (float) $stock[$loc]->lo : null,
                // Buyers asking per unit on the shelf. High means a lead here
                // meets competition for stock; it is still where the interest
                // is, and it is where a realtor who HAS a unit wins fastest.
                'pressure' => $available > 0 ? $w->c / $available : null,
            ];
        })->values()
            ->filter(fn ($r) => $r['available'] > 0)
            ->sortByDesc('pressure')
            ->values();

        if ($rows->isEmpty()) {
            return $this->nothing();
        }

        $top = $rows->first();

        return [
            'kind' => 'ranking',
            'headline' => $top['location'].' is where the pressure is',
            'narrative' => $this->b((string) $top['demand']).' buyers have asked for '
                .$this->b($top['location']).' in the last four months against '
                .$this->b($top['available'].' '.($top['available'] === 1 ? 'unit' : 'units'))
                .' on the shelf — roughly '.$this->b(round($top['pressure'], 1).' buyers per unit')
                .'. Their average stated budget is '.$this->b(Fmt::moneyShort($top['budget']))
                .($top['from'] ? ', and stock there starts at '.$this->b(Fmt::money($top['from'])) : '')
                .'. That is the market as a whole, not any one realtor\'s book: it tells you where '
                .'the interest is, and the table below shows where it is thinnest on supply.',
            'columns' => ['Area', 'Buyers asking', 'Still open', 'Units available', 'Avg budget'],
            'rows' => $rows->take(10)->map(fn ($r) => [
                $r['location'],
                Fmt::num($r['demand']),
                Fmt::num($r['open']),
                Fmt::num($r['available']),
                Fmt::moneyShort($r['budget']),
            ])->all(),
            'items' => [],
        ];
    }

    private function whatBuyersWant(): array
    {
        $byType = Lead::query()
            ->whereNotNull('wants_type')
            ->where('created_at', '>=', now()->subDays(120))
            ->selectRaw('wants_type t, COUNT(*) c, AVG(budget) avg_budget')
            ->groupBy('t')->orderByDesc('c')->get();

        $prev = Lead::query()
            ->whereNotNull('wants_location')
            ->whereBetween('created_at', [now()->subDays(240), now()->subDays(120)])
            ->selectRaw('wants_location loc, COUNT(*) c')
            ->groupBy('loc')->pluck('c', 'loc');

        $now = Lead::query()
            ->whereNotNull('wants_location')
            ->where('created_at', '>=', now()->subDays(120))
            ->selectRaw('wants_location loc, COUNT(*) c')
            ->groupBy('loc')->pluck('c', 'loc');

        if ($byType->isEmpty() && $now->isEmpty()) {
            return $this->nothing();
        }

        // Movement, not level. An area that is already the biggest is not news;
        // one that has grown by half since last quarter is.
        $rising = collect($now)
            ->map(fn ($c, $loc) => [
                'loc' => $loc,
                'now' => (int) $c,
                'was' => (int) ($prev[$loc] ?? 0),
                'change' => ((int) $c) - ((int) ($prev[$loc] ?? 0)),
            ])
            ->filter(fn ($r) => $r['now'] >= 8)
            ->sortByDesc('change')
            ->values();

        $up = $rising->first();
        $topType = $byType->first();

        return [
            'kind' => 'ranking',
            'headline' => $up
                ? 'Interest is moving towards '.$up['loc']
                : 'What buyers are asking for',
            'narrative' => ($up
                    ? $this->b($up['loc']).' has had '.$this->b((string) $up['now'])
                      .' buyers asking in the last four months, against '.$this->b((string) $up['was'])
                      .' in the four before that — a swing of '
                      .$this->b(($up['change'] >= 0 ? '+' : '').$up['change']).'. '
                    : '')
                .($topType
                    ? 'The most-requested type overall is '.$this->b($topType->t).' with '
                      .$this->b(Fmt::num((int) $topType->c)).' buyers, average budget '
                      .$this->b(Fmt::moneyShort((float) $topType->avg_budget)).'. '
                    : '')
                .'These are the whole market\'s enquiries, so they show you where to aim rather than '
                .'how you are doing.',
            'columns' => ['Area', 'Last 4 months', 'Previous 4', 'Movement'],
            'rows' => $rising->take(10)->map(fn ($r) => [
                $r['loc'],
                Fmt::num($r['now']),
                Fmt::num($r['was']),
                ($r['change'] >= 0 ? '+' : '').$r['change'],
            ])->all(),
            'items' => [],
        ];
    }

    /* ------------------------------------------------------- their book */

    private function myBook(): array
    {
        $id = $this->realtor->id;

        $leads = Lead::where('assigned_agent_id', $id);
        $total = (clone $leads)->count();
        $open = (clone $leads)->where('is_open', true)->count();
        $won = (clone $leads)->where('status', 'won')->count();

        $investors = Customer::where('agent_id', $id)->count();
        $commitments = Deal::where('agent_id', $id);
        $live = (clone $commitments)->live()->count();
        $done = (clone $commitments)->where('status', 'completed')->count();

        // Their own conversion by area — the half that IS theirs to see.
        $byArea = Lead::where('assigned_agent_id', $id)
            ->whereNotNull('wants_location')
            ->selectRaw("wants_location loc, COUNT(*) c,
                SUM(CASE WHEN status='won' THEN 1 ELSE 0 END) won")
            ->groupBy('loc')->having(DB::raw('COUNT(*)'), '>=', 3)
            ->orderByDesc('won')->get();

        if ($total === 0) {
            return [
                'kind' => 'none',
                'headline' => 'No leads on your book yet',
                'narrative' => 'Once buyers start arriving through your link I can show you how they '
                    .'convert and where they come from. In the meantime, ask me where the market is '
                    .'asking to buy — that part does not depend on your own numbers.',
                'items' => [],
            ];
        }

        $best = $byArea->first();

        return [
            'kind' => 'ranking',
            'headline' => Fmt::num($open).' open '.($open === 1 ? 'lead' : 'leads').' on your book',
            'narrative' => 'You have brought in '.$this->b(Fmt::num($total)).' leads in total, of '
                .'which '.$this->b(Fmt::num($open)).' are still open and '.$this->b(Fmt::num($won))
                .' converted — a close rate of '.$this->b(Fmt::pct($total ? $won / $total : 0, 1)).'. '
                .'That has produced '.$this->b(Fmt::num($investors)).' registered '
                .($investors === 1 ? 'investor' : 'investors').', '.$this->b(Fmt::num($live))
                .' live '.($live === 1 ? 'commitment' : 'commitments').' and '
                .$this->b(Fmt::num($done)).' settled.'
                .($best
                    ? ' Your strongest area is '.$this->b($best->loc).', where '
                      .$this->b($best->won.' of '.$best->c).' have closed.'
                    : ''),
            'columns' => ['Area', 'Your leads', 'Closed', 'Your close rate'],
            'rows' => $byArea->take(10)->map(fn ($r) => [
                $r->loc,
                Fmt::num((int) $r->c),
                Fmt::num((int) $r->won),
                Fmt::pct($r->c ? $r->won / $r->c : 0, 1),
            ])->all(),
            'items' => [],
        ];
    }

    private function myMoney(): array
    {
        $payouts = CommissionPayout::where('agent_id', $this->realtor->id);

        $earned = (float) (clone $payouts)->sum('amount');
        $paid = (float) (clone $payouts)->where('status', 'paid')->sum('amount');
        $owed = (float) (clone $payouts)->where('status', 'owed')->sum('amount');
        $count = (clone $payouts)->count();

        if ($count === 0) {
            return [
                'kind' => 'none',
                'headline' => 'Nothing accrued yet',
                'narrative' => 'Commission accrues when a commitment you brought in is approved — '
                    .'that is the moment the rate is frozen onto the sale and it becomes yours '
                    .'regardless of what happens to the listing afterwards.',
                'items' => [],
            ];
        }

        return [
            'kind' => 'file',
            'headline' => Fmt::money($owed).' owed to you',
            'narrative' => 'Across '.$this->b(Fmt::num($count)).' '
                .($count === 1 ? 'sale' : 'sales').' you have earned '
                .$this->b(Fmt::money($earned)).', of which '.$this->b(Fmt::money($paid))
                .' has been paid and '.$this->b(Fmt::money($owed)).' is still outstanding. '
                .'Each rate was frozen onto its sale at approval, so none of these figures can '
                .'move if a property is repriced later. Your statement lists them line by line.',
            'items' => [],
        ];
    }

    /* --------------------------------------------------------- the stock */

    private function whatIsMoving(): array
    {
        $rows = Property::query()->onMarket()
            ->selectRaw('location, type, COUNT(*) c, MIN(price) lo, AVG(commission_rate) rate,
                SUM(inquiries) inq')
            ->groupBy('location', 'type')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('inq')
            ->limit(10)->get();

        if ($rows->isEmpty()) {
            return $this->nothing();
        }

        $t = $rows->first();

        return [
            'kind' => 'ranking',
            'headline' => 'Most-enquired stock you can sell today',
            'narrative' => $this->b($t->type.'s in '.$t->location).' have drawn '
                .$this->b(Fmt::num((int) $t->inq)).' enquiries across '
                .$this->b($t->c.' available '.($t->c === 1 ? 'unit' : 'units'))
                .', starting at '.$this->b(Fmt::money((float) $t->lo))
                .' and paying '.$this->b(number_format((float) $t->rate * 100, 1).'%')
                .' commission. Enquiries are the clearest signal of what will move.',
            'columns' => ['Area', 'Type', 'Available', 'From', 'Commission', 'Enquiries'],
            'rows' => $rows->map(fn ($r) => [
                $r->location, $r->type, Fmt::num((int) $r->c),
                Fmt::moneyShort((float) $r->lo),
                number_format((float) $r->rate * 100, 1).'%',
                Fmt::num((int) $r->inq),
            ])->all(),
            'items' => [],
        ];
    }

    private function budgetPicture(): array
    {
        $bands = Lead::query()
            ->where('budget', '>', 0)
            ->where('created_at', '>=', now()->subDays(180))
            ->selectRaw("CASE
                WHEN budget < 25000000 THEN 'Under ₦25m'
                WHEN budget < 60000000 THEN '₦25m – ₦60m'
                WHEN budget < 120000000 THEN '₦60m – ₦120m'
                ELSE 'Over ₦120m' END band, COUNT(*) c")
            ->groupBy('band')->orderByDesc('c')->get();

        if ($bands->isEmpty()) {
            return $this->nothing();
        }

        $top = $bands->first();
        $total = $bands->sum('c');

        return [
            'kind' => 'ranking',
            'headline' => 'Most buyers are in the '.$top->band.' band',
            'narrative' => $this->b(Fmt::num((int) $top->c)).' of '.$this->b(Fmt::num((int) $total))
                .' buyers in the last six months stated a budget of '.$this->b($top->band)
                .' — '.$this->b(Fmt::pct($top->c / max(1, $total), 0)).' of everyone who gave a '
                .'figure. Stock priced into the band with the most buyers is stock that moves.',
            'columns' => ['Budget band', 'Buyers', 'Share'],
            'rows' => $bands->map(fn ($r) => [
                $r->band, Fmt::num((int) $r->c), Fmt::pct($r->c / max(1, $total), 0),
            ])->all(),
            'items' => [],
        ];
    }

    /* ------------------------------------------------------ open-ended */

    private function openQuestion(string $question): array
    {
        $written = $this->narrator->answer($question, $this->facts());

        if ($written === null) {
            return $this->nothing();
        }

        return [
            'kind' => 'written',
            'headline' => 'From your figures',
            'narrative' => $written,
            'items' => [],
        ];
    }

    private function nothing(): array
    {
        return [
            'kind' => 'none',
            'headline' => "I don't have the data for that",
            'narrative' => 'I answer from what this platform records — the market\'s enquiries, the '
                .'stock on the shelf and your own book. I would rather say so than hand you a number '
                .'that looks right and is not. Try asking where buyers are looking, what stock is '
                .'moving, or how your own leads are converting.',
            'items' => [],
        ];
    }

    /**
     * Figures only, and scoped the same way the resolvers are.
     *
     * Market-level aggregates plus this realtor's own totals. Nothing here
     * names another realtor or exposes anyone else's book.
     */
    private function facts(): array
    {
        $id = $this->realtor->id;
        $leads = Lead::where('assigned_agent_id', $id);
        $payouts = CommissionPayout::where('agent_id', $id);
        $stock = Property::query()->onMarket();

        $topAreas = Lead::whereNotNull('wants_location')
            ->where('created_at', '>=', now()->subDays(120))
            ->selectRaw('wants_location loc, COUNT(*) c')
            ->groupBy('loc')->orderByDesc('c')->limit(6)->get()
            ->map(fn ($r) => $r->loc.' ('.$r->c.')')->implode(', ');

        $stockByArea = Property::query()->onMarket()
            ->selectRaw('location, COUNT(*) c')
            ->groupBy('location')->orderByDesc('c')->limit(6)->get()
            ->map(fn ($r) => $r->location.' ('.$r->c.')')->implode(', ');

        $total = (clone $leads)->count();
        $won = (clone $leads)->where('status', 'won')->count();

        return [
            'market_areas_buyers_are_asking_for' => $topAreas ?: 'none recorded',
            'market_stock_available_by_area' => $stockByArea ?: 'none available',
            'market_properties_available' => (clone $stock)->count(),
            'market_cheapest_property' => Fmt::money((float) (clone $stock)->min('price')),
            'market_average_property_price' => Fmt::money((float) (clone $stock)->avg('price')),

            'my_name' => $this->realtor->name,
            'my_referral_code' => $this->realtor->code ?? 'not issued yet',
            'my_total_leads' => $total,
            'my_open_leads' => (clone $leads)->where('is_open', true)->count(),
            'my_converted_leads' => $won,
            'my_close_rate' => Fmt::pct($total ? $won / $total : 0, 1),
            'my_investors' => Customer::where('agent_id', $id)->count(),
            'my_live_commitments' => Deal::where('agent_id', $id)->live()->count(),
            'my_completed_commitments' => Deal::where('agent_id', $id)->where('status', 'completed')->count(),
            'my_commission_earned' => Fmt::money((float) (clone $payouts)->sum('amount')),
            'my_commission_owed' => Fmt::money((float) (clone $payouts)->where('status', 'owed')->sum('amount')),
        ];
    }

    private function b(string $s): string
    {
        return '**'.$s.'**';
    }

    public function suggestions(): array
    {
        return [
            'Which location should I focus my marketing on?',
            'Which areas are buyers becoming interested in?',
            'What stock is moving fastest right now?',
            'How are my own leads converting?',
            'What budgets are buyers actually working with?',
            'How much commission am I owed?',
        ];
    }
}
