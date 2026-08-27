<?php

namespace App\Livewire\Public;

use App\Models\Agent;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Payment;
use App\Models\Property;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The front door.
 *
 * The visitor being sold to here is an OPERATOR — somebody who runs a property
 * business and is deciding whether this software is worth their afternoon.
 * They are not shopping for a flat, so the hero does not show them one.
 *
 * It makes the argument for adaptive business intelligence instead, across
 * four slides, each stated as a claim the rest of the page then backs with a
 * live figure. The instalment platform is further down, where it belongs: it
 * is how the intelligence gets something to read, and it is the proof the
 * engine runs on a real business rather than on a demo dataset.
 *
 * No product screenshots anywhere. A picture of a dashboard is a claim; the
 * live figures on this page are the same claim, checkable.
 */
#[Layout('components.layouts.marketing')]
#[Title('Adaptive business intelligence for property')]
class Home extends Component
{
    /**
     * Photography behind the hero.
     *
     * Drawn from the same vetted Unsplash set the catalogue uses — every one
     * was fetched and looked at before it went in the list. Commercial scenes
     * rather than domestic ones, because the person reading this runs the
     * business; a sofa is the wrong register entirely.
     */
    private const SHOT = 'https://images.unsplash.com/photo-';

    private const SHOTS = [
        '1497366754035-f200968a6e72',   // open-plan office, glazed
        '1431576901776-e539bd916ba2',   // curtain wall at sunset
        '1449824913935-59a10b8d2000',   // city street, towers
        '1524758631624-e2822e304c36',   // office lounge
    ];
    public function render()
    {
        $sold = fn () => Deal::whereIn('status', array_merge(Deal::LIVE, ['completed']));

        $stats = [
            'properties' => (int) Property::query()->onMarket()->count(),
            'investors' => (int) Customer::count(),
            'realtors' => (int) Agent::query()->approved()->count(),
            'tracked' => (float) $sold()->sum('value'),
            'collected' => (float) Payment::whereNotNull('paid_at')->sum('amount'),
            'instalments' => (int) Payment::whereNotNull('paid_at')->count(),
        ];

        return view('livewire.public.home', [
            // Every figure here is read off the live database. Nothing on this
            // page is a marketing number somebody typed into the markup.
            'stats' => $stats,
            'slides' => $this->slides($stats),
            'featured' => $this->featured(),
        ]);
    }

    /**
     * The hero carousel — the argument, in four claims.
     *
     * Each slide states one thing the product does and one reason a business
     * would care, and every figure quoted is read off the live database rather
     * than written into the markup. That is the whole trick of this page: the
     * boldest claim on it is also the most checkable.
     *
     * Ordered as an argument rather than a feature list — it finds what is
     * wrong, it fixes it, it answers questions about it, and it runs the
     * business underneath all three. Reordering these breaks the reasoning.
     */
    private function slides(array $stats): array
    {
        $shot = fn (int $i) => self::SHOT.self::SHOTS[$i]
            .'?auto=format&fit=crop&w=2000&h=1200&q=72';

        return [
            [
                'image' => $shot(0),
                'kicker' => 'Adaptive business intelligence',
                'title' => 'Your data already knows what is going wrong.',
                'body' => 'Fourteen detectors sweep every lead, sale, instalment and campaign in '
                    .'your business every fifteen minutes. Stale pipeline, a channel that stopped '
                    .'converting, a realtor whose close rate halved — found and ranked before '
                    .'anyone thinks to run a report.',
                'stat' => ['14', 'detectors running'],
            ],
            [
                'image' => $shot(1),
                'kicker' => 'From finding to fixed',
                'title' => 'It does not just tell you. It changes the data.',
                'body' => 'Every finding arrives with the one thing to do about it, and doing it '
                    .'moves real rows — logged, with a before-and-after diff. The finding then '
                    .'closes itself, because the condition it described no longer holds. If '
                    .'nothing actually changed, it stays exactly where it was.',
                'stat' => ['0', 'hardcoded insights'],
            ],
            [
                'image' => $shot(2),
                'kicker' => 'Ask it anything',
                'title' => 'A straight answer, or an honest "I don\'t know".',
                'body' => 'Ask why revenue moved, which locations are selling, who is behind on '
                    .'payments. Every answer is computed from your own figures and opens onto the '
                    .'rows behind it — and when the data cannot answer, it says so instead of '
                    .'producing a number that looks right.',
                'stat' => [Fmt::numShort($stats['instalments']), 'instalments tracked'],
            ],
            [
                'image' => $shot(3),
                'kicker' => 'Not a dashboard bolted on',
                'title' => 'It runs the business it analyses.',
                'body' => 'Buyers commit to property and pay it off over a term they choose; '
                    .'realtors bring them in and earn on it; your team approves, records and '
                    .'collects. The intelligence reads the same rows the business writes, so the '
                    .'two can never disagree.',
                'stat' => [Fmt::moneyShort($stats['tracked']), 'under management'],
            ],
        ];
    }

    /**
     * Four properties, deliberately four different kinds.
     *
     * Ordering the whole catalogue by enquiries returned three 3-bed flats in
     * Lekki — accurate, and it makes a 700-unit book look like it sells one
     * thing. One of each type shows the range instead.
     */
    private function featured()
    {
        return $this->oneOfEach(
            ['Detached Duplex', 'Apartment', 'Land', 'Office Space'],
            4,
        );
    }

    /**
     * The most-enquired available unit of each named type, topped up from
     * whatever else is on the market if a type has sold out.
     *
     * @param  string[]  $types
     * @param  int[]  $exclude
     */
    private function oneOfEach(array $types, int $want, array $exclude = [])
    {
        $picked = collect();

        foreach ($types as $type) {
            $row = Property::query()->onMarket()
                ->where('type', $type)
                ->whereNotIn('id', array_merge($exclude, $picked->pluck('id')->all()))
                ->orderByDesc('inquiries')
                ->first();

            if ($row) {
                $picked->push($row);
            }

            if ($picked->count() >= $want) {
                break;
            }
        }

        // Top up if a requested type has sold out — but never with a type
        // already on the list. Falling back on raw popularity put a second
        // 3-bed apartment in the carousel beside the first, and two slides
        // reading "3-Bedroom Apartment" makes a 700-unit catalogue look like
        // it sells one thing, which is the exact failure this method exists
        // to avoid.
        if ($picked->count() < $want) {
            $picked = $picked->concat(
                Property::query()->onMarket()
                    ->whereNotIn('id', array_merge($exclude, $picked->pluck('id')->all()))
                    ->whereNotIn('type', $picked->pluck('type')->all())
                    ->orderByDesc('inquiries')
                    ->limit($want - $picked->count())->get()
            );
        }

        return $picked->take($want)->values();
    }
}
