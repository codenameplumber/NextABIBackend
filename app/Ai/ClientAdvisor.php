<?php

namespace App\Ai;

use App\Models\Customer;
use App\Models\Property;
use App\Support\Fmt;
use App\Support\Purchase;

/**
 * Ask NexABI — the buyer's side.
 *
 * A person buying property on a schedule has a narrow set of questions and
 * they are almost all arithmetic: what can I afford at this monthly figure,
 * what would this unit cost me over that term, how much do I still owe, what
 * happens if I am late. The answers exist in the database; what people lack is
 * a way to ask.
 *
 * So the affordability questions are COMPUTED, not narrated — the engine finds
 * the actual units that fit the number and lists them. Only the open-ended
 * questions go to the model, and it receives figures rather than rows, so it
 * can phrase but never invent a price.
 *
 * What it will not do is advise. It will not say whether a buyer should
 * stretch to a bigger unit or whether Lekki is a good investment, because
 * nothing in this database knows that and a confident answer would be a
 * financial opinion dressed as a calculation.
 */
class ClientAdvisor
{
    public function __construct(
        private Customer $customer,
        private ?NarratorContract $narrator = null,
    ) {
        $this->narrator ??= app(NarratorContract::class);
    }

    /* -------------------------------------------------------------- ask */

    public function ask(string $question): array
    {
        $q = mb_strtolower(trim($question));

        $answer = match (true) {
            /*
             | Order matters, and this pair is why.
             |
             | "Which areas are most affordable?" contains the word "afford",
             | so with affordability first it was answered by asking the buyer
             | for a monthly figure they had not mentioned and did not need to.
             | A question about WHERE, with no amount in it, is a question
             | about the market — so it is routed on the absence of a number
             | rather than on word order.
             */
            $this->isAbout($q, 'where|which (area|location)|cheapest|most affordable|price.*(area|location)')
                && ! $this->parseMoney($q)
                => $this->byLocation(),

            $this->isAbout($q, 'afford|budget|can i (get|buy|pay)|what.*(with|for) *(₦|ngn|n?[0-9])|monthly|per month|a month')
                => $this->affordability($q),

            $this->isAbout($q, 'owe|balance|outstanding|left to pay|how much.*left|remaining|my payment|next payment|when.*due')
                => $this->myFile(),

            $this->isAbout($q, 'late|miss(ed)? a payment|behind|overdue|what happens if')
                => $this->beingLate(),

            $this->isAbout($q, 'term|how long|months|instal?ment plan|spread')
                => $this->terms($q),

            default => null,
        };

        return $answer ?? $this->openQuestion($question);
    }

    private function isAbout(string $q, string $pattern): bool
    {
        return (bool) preg_match('/'.$pattern.'/i', $q);
    }

    /* ---------------------------------------------------- affordability */

    /**
     * "What can I afford at ₦800,000 a month?"
     *
     * The single most useful question this product can answer, and the one a
     * catalogue with a price filter cannot: the buyer knows what they can pay
     * each month, not what they can pay in total. Filtering by price is asking
     * them to do the division themselves and get it wrong.
     */
    private function affordability(string $q): ?array
    {
        $amount = $this->parseMoney($q);

        if (! $amount) {
            return [
                'kind' => 'prompt',
                'headline' => 'How much a month were you thinking?',
                'narrative' => 'Tell me a monthly figure and I will show you exactly what fits it — '
                    .'for example, **what can I afford at ₦500,000 a month**. I work it out from the '
                    .'longest term each property offers, which is the smallest monthly payment on it.',
                'items' => [],
            ];
        }

        // Monthly unless the question says otherwise. Somebody typing a figure
        // into a question about affording a house is almost always telling you
        // what they can pay each month, not the whole price.
        $isTotal = (bool) preg_match('/\b(total|outright|cash|altogether|in full|overall|budget of)\b/i', $q)
            && ! preg_match('/\b(a|per|each|every) month|monthly\b/i', $q);

        $matches = Property::query()->onMarket()
            ->orderBy('price')
            ->get()
            ->map(function (Property $p) {
                $terms = $p->durationOptions();
                $longest = max($terms);

                return [
                    'property' => $p,
                    'months' => $longest,
                    'perMonth' => $p->instalmentFor($longest),
                ];
            })
            ->filter(fn ($r) => $isTotal
                ? $r['property']->price <= $amount
                : $r['perMonth'] <= $amount)
            ->values();

        $label = $isTotal ? 'a total budget of '.Fmt::money($amount) : Fmt::money($amount).' a month';

        if ($matches->isEmpty()) {
            $cheapest = Property::query()->onMarket()->orderBy('price')->first();
            $lowest = $cheapest
                ? $cheapest->instalmentFor(max($cheapest->durationOptions()))
                : null;

            return [
                'kind' => 'none',
                'headline' => 'Nothing on the market fits that yet',
                'narrative' => 'Nothing currently available comes in under '.$this->b($label).'.'
                    .($lowest
                        ? ' The most affordable unit we have is '.$this->b($cheapest->title)
                          .' in '.$this->b($cheapest->location).', which works out at '
                          .$this->b(Fmt::money($lowest)).' a month over '
                          .$this->b(max($cheapest->durationOptions()).' months').'.'
                        : ''),
                'items' => [],
            ];
        }

        $top = $matches->sortByDesc(fn ($r) => $r['property']->price)->take(6)->values();
        $locations = $matches->pluck('property.location')->unique()->take(4)->implode(', ');

        return [
            'kind' => 'properties',
            'headline' => $matches->count().' '.($matches->count() === 1 ? 'property fits' : 'properties fit')
                .' '.$label,
            'narrative' => 'On '.$this->b($label).' you have '.$this->b((string) $matches->count())
                .' '.($matches->count() === 1 ? 'option' : 'options').' available right now, across '
                .$this->b($locations).'. The figures below are at the longest term each property '
                .'offers — pick a shorter term and the monthly payment goes up, but you own it sooner. '
                .'Nothing here is a quote; committing is what fixes your schedule.',
            'items' => $top->map(fn ($r) => [
                'title' => $r['property']->title,
                'sub' => $r['property']->location.' · '.$r['property']->type,
                'value' => Fmt::money($r['perMonth']).'/mo',
                'note' => Fmt::money($r['property']->price).' over '.$r['months'].' months',
                'ref' => $r['property']->ref,
            ])->all(),
            'more' => max(0, $matches->count() - $top->count()),
        ];
    }

    /** Reads ₦2,500,000 · 2.5m · 500k · "2 million" · "800 thousand". */
    private function parseMoney(string $q): ?float
    {
        if (preg_match('/([0-9][0-9,\.]*)\s*(m|million)\b/i', $q, $m)) {
            return (float) str_replace(',', '', $m[1]) * 1_000_000;
        }

        if (preg_match('/([0-9][0-9,\.]*)\s*(k|thousand)\b/i', $q, $m)) {
            return (float) str_replace(',', '', $m[1]) * 1_000;
        }

        if (preg_match('/(?:₦|ngn|naira)?\s*([0-9][0-9,]{2,})/i', $q, $m)) {
            $v = (float) str_replace(',', '', $m[1]);

            // A bare "12" in "over 12 months" is not a budget.
            return $v >= 10_000 ? $v : null;
        }

        return null;
    }

    /* ------------------------------------------------------- their file */

    private function myFile(): array
    {
        $p = Purchase::for($this->customer);

        if (! $p->deal) {
            return [
                'kind' => 'none',
                'headline' => 'You have not committed to anything yet',
                'narrative' => 'Once you commit to a property and it is approved, your instalment '
                    .'schedule opens here and I can answer questions about it. Until then, ask me '
                    .'what you can afford at a given monthly figure.',
                'items' => [],
            ];
        }

        $next = $p->next();
        $late = $p->lateCount();

        return [
            'kind' => 'file',
            'headline' => $p->settled()
                ? 'Your purchase is fully settled'
                : Fmt::money($p->outstanding()).' still to pay',
            'narrative' => $p->settled()
                ? 'All '.$this->b((string) $p->payments->count()).' instalments on '
                  .$this->b($p->property?->title ?? 'your property').' have been received. Your '
                  .'documents are in the Documents tab.'
                : 'You have paid '.$this->b(Fmt::money($p->paidAmount())).' of '
                  .$this->b(Fmt::money($p->total())).' on '
                  .$this->b($p->property?->title ?? 'your property').' — that is '
                  .$this->b(Fmt::pct($p->progress(), 0)).' of the way there. '
                  .($next
                      ? 'Your next instalment is '.$this->b(Fmt::money($next->amount))
                        .', due '.$this->b(Fmt::date($next->due_at)).'.'
                      : '')
                  .($late
                      ? ' '.$this->b($late.' '.($late === 1 ? 'payment is' : 'payments are').' past due')
                        .' — settling those keeps your handover on track, and your realtor can '
                        .'rearrange the remaining schedule if the timing is hard.'
                      : ''),
            'items' => [],
        ];
    }

    private function beingLate(): array
    {
        return [
            'kind' => 'explain',
            'headline' => 'A late instalment is a conversation, not a penalty',
            'narrative' => 'Your payment status is worked out from the due date against today, so a '
                .'missed instalment shows as overdue on your schedule and on ours the same day — '
                .'there is no hidden clock. Nothing is repossessed automatically and no fee is added '
                .'by the system. What happens next is a conversation with your realtor, who can ask '
                .'for the remaining instalments to be rearranged over a longer term so the monthly '
                .'figure comes down. The earlier you raise it, the more room there is to do that.',
            'items' => [],
        ];
    }

    /* -------------------------------------------------- terms and areas */

    private function terms(string $q): array
    {
        return [
            'kind' => 'explain',
            'headline' => 'You choose the term, within what the property offers',
            'narrative' => 'Terms run from '.$this->b('3 months to 60 months').', and each property '
                .'sets its own range — larger units generally allow longer. The monthly figure is '
                .'simply the price divided by the number of months you pick, and the schedule adds '
                .'up to the price exactly: there is no interest and no arrangement fee. A longer term '
                .'means a smaller monthly payment and a later handover; a shorter one means the '
                .'opposite. Open any property and move the term picker to see both.',
            'items' => [],
        ];
    }

    private function byLocation(): array
    {
        $rows = Property::query()->onMarket()
            ->selectRaw('location, COUNT(*) c, MIN(price) lo, AVG(price) avg')
            ->groupBy('location')
            ->orderBy('lo')
            ->get();

        if ($rows->isEmpty()) {
            return $this->nothing();
        }

        return [
            'kind' => 'properties',
            'headline' => 'Where the market sits right now',
            'narrative' => 'Entry prices by area, cheapest first. '
                .$this->b($rows->first()->location).' starts at '
                .$this->b(Fmt::money((float) $rows->first()->lo))
                .', and '.$this->b($rows->last()->location).' starts at '
                .$this->b(Fmt::money((float) $rows->last()->lo)).'. Ask me what fits a monthly '
                .'figure and I will narrow it to the actual units.',
            'items' => $rows->map(fn ($r) => [
                'title' => $r->location,
                'sub' => $r->c.' available',
                'value' => 'from '.Fmt::money((float) $r->lo),
                'note' => 'average '.Fmt::moneyShort((float) $r->avg),
                'ref' => null,
            ])->all(),
        ];
    }

    /* ------------------------------------------------------ open-ended */

    /**
     * Everything else, phrased by the model from computed figures.
     *
     * The buyer's own balance is deliberately included, so "is my place a good
     * deal" gets answered against what they are actually paying rather than in
     * the abstract.
     */
    private function openQuestion(string $question): array
    {
        $written = $this->narrator->answer($question, $this->facts());

        if ($written === null) {
            return $this->nothing();
        }

        return [
            'kind' => 'written',
            'headline' => 'Here is what I can tell you',
            'narrative' => $written,
            'items' => [],
        ];
    }

    private function nothing(): array
    {
        return [
            'kind' => 'none',
            'headline' => "I don't have the data for that",
            'narrative' => 'I can only answer from what this platform actually records — the '
                .'catalogue, the terms on offer and your own payment file. I would rather say so '
                .'than give you a number that looks right and is not. Your realtor can answer '
                .'anything about the property itself, and their details are at the top of every page.',
            'items' => [],
        ];
    }

    /** Figures only. The model never sees a row, so it cannot invent a price. */
    private function facts(): array
    {
        $p = Purchase::for($this->customer);
        $stock = Property::query()->onMarket();

        $byType = Property::query()->onMarket()
            ->selectRaw('type, COUNT(*) c, MIN(price) lo, MAX(price) hi')
            ->groupBy('type')->get()
            ->map(fn ($r) => $r->type.': '.$r->c.' from '.Fmt::moneyShort((float) $r->lo)
                .' to '.Fmt::moneyShort((float) $r->hi))
            ->implode(' | ');

        return [
            'properties_available_now' => (clone $stock)->count(),
            'cheapest_property' => Fmt::money((float) (clone $stock)->min('price')),
            'most_expensive_property' => Fmt::money((float) (clone $stock)->max('price')),
            'average_property_price' => Fmt::money((float) (clone $stock)->avg('price')),
            'stock_by_type' => $byType,
            'shortest_term_offered_months' => 3,
            'longest_term_offered_months' => 60,
            'interest_charged' => 'none — the schedule sums to the price exactly',
            'my_realtor' => $this->customer->realtor?->name ?? 'not yet assigned',
            'my_property' => $p->property?->title ?? 'none committed yet',
            'my_total_price' => $p->deal ? Fmt::money($p->total()) : 'n/a',
            'my_amount_paid' => $p->deal ? Fmt::money($p->paidAmount()) : 'n/a',
            'my_outstanding' => $p->deal ? Fmt::money($p->outstanding()) : 'n/a',
            'my_instalments_paid' => $p->deal ? $p->paid()->count().' of '.$p->payments->count() : 'n/a',
            'my_payments_overdue' => $p->deal ? $p->lateCount() : 0,
        ];
    }

    private function b(string $s): string
    {
        return '**'.$s.'**';
    }

    /** Questions worth putting on the screen, tuned to where this buyer is. */
    public function suggestions(): array
    {
        $p = Purchase::for($this->customer);

        $base = [
            'What can I afford at ₦500,000 a month?',
            'How do the instalment terms work?',
            'Which areas are most affordable right now?',
            'What happens if I miss a payment?',
        ];

        if ($p->deal) {
            array_unshift($base, 'How much do I still owe?');
        }

        return $base;
    }
}
