<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\CommissionPayout;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Feedback;
use App\Models\Property;
use App\Models\PromotionalMaterial;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * The platform layer on top of the simulated business.
 *
 * BusinessSimulator produces twenty months of trading. This turns that into a
 * platform somebody can sign into: historical deals get their frozen commission
 * rate and an accrual, a handful of live commitments sit in the approval queue,
 * some investors are deliberately left unassigned, and there are materials for
 * a realtor to send.
 *
 * Separate from the simulator on purpose. The simulator models a business; this
 * models the product wrapped around it, and mixing the two makes it impossible
 * to change either without breaking the other.
 */
class PlatformSeeder
{
    private Rng $r;

    public function __construct(private Tenant $tenant)
    {
        // Fixed seed: the demo has to be byte-identical on every run, or a
        // reviewer following a script finds different numbers than the script.
        $this->r = new Rng(20260826);
    }

    public function run(): array
    {
        return [
            'commissions' => $this->backfillCommission(),
            'pending' => $this->plantPendingCommitments(),
            'unassigned' => $this->plantUnassignedInvestors(),
            'materials' => $this->makeMaterials(),
            'feedback' => $this->stampFeedbackTriggers(),
        ];
    }

    /**
     * Give every historical sale the rate it was sold at, and an accrual.
     *
     * The rate is copied from the property ONCE here, exactly as approval would
     * have done at the time — after this the commitment carries its own rate and
     * the property is free to change.
     */
    private function backfillCommission(): int
    {
        $rates = Property::pluck('commission_rate', 'id');
        $n = 0;

        Deal::query()
            ->whereIn('status', array_merge(Deal::LIVE, ['completed']))
            ->whereNull('commission_rate')
            ->chunkById(500, function ($deals) use ($rates, &$n) {
                $payouts = [];

                foreach ($deals as $deal) {
                    $rate = Property::normaliseRate($rates[$deal->property_id] ?? 0.05);
                    $commission = (float) $deal->value * $rate;

                    $deal->forceFill([
                        'commission_rate' => $rate,
                        'commission' => $commission,
                        'committed_at' => $deal->opened_at,
                        'approved_at' => $deal->closed_at ?? $deal->opened_at,
                        'duration_months' => $deal->duration_months
                            ?? max(1, (int) $deal->payments()->count()),
                    ])->save();

                    if ($deal->agent_id) {
                        $payouts[] = [
                            'tenant_id' => $deal->tenant_id,
                            'agent_id' => $deal->agent_id,
                            'deal_id' => $deal->id,
                            'amount' => $commission,
                            'rate' => $rate,
                            // Older sales have been settled; recent ones have
                            // not, so Finance opens with real work to do.
                            'status' => $deal->closed_at?->lt(now()->subDays(45)) ? 'paid' : 'owed',
                            'paid_at' => $deal->closed_at?->lt(now()->subDays(45))
                                ? $deal->closed_at->copy()->addDays(7) : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $n++;
                }

                foreach (array_chunk($payouts, 400) as $chunk) {
                    CommissionPayout::insertOrIgnore($chunk);
                }
            });

        return $n;
    }

    /**
     * A few live commitments waiting on an admin.
     *
     * The approval queue is one of the two screens an admin opens every
     * morning, and a queue that is empty on first login teaches nobody what it
     * is for. One is deliberately left with no realtor, so the blocker and the
     * inline fix are both visible.
     */
    private function plantPendingCommitments(): int
    {
        $investors = Customer::query()->assigned()->inRandomOrder()->limit(5)->get();
        $stock = Property::query()->onMarket()->inRandomOrder()->limit(6)->get();

        if ($investors->isEmpty() || $stock->count() < 6) {
            return 0;
        }

        $made = 0;

        DB::transaction(function () use ($investors, $stock, &$made) {
            foreach ($investors as $i => $investor) {
                $property = $stock[$i];
                $months = $property->durationOptions();

                Deal::create([
                    'tenant_id' => $this->tenant->id,
                    'customer_id' => $investor->id,
                    'property_id' => $property->id,
                    'agent_id' => $investor->agent_id,
                    'value' => $property->price,
                    'commission' => 0,
                    'duration_months' => $this->r->pick($months),
                    'status' => 'pending',
                    'won' => false,
                    'opened_at' => now()->subDays($this->r->int(1, 6)),
                    'committed_at' => now()->subDays($this->r->int(1, 6)),
                    'source' => $investor->heard_about_us,
                    'location' => $property->location,
                    'property_type' => $property->type,
                ]);

                $property->forceFill(['status' => 'reserved'])->save();
                $made++;
            }

            // One with nobody attached, so the blocker is on screen from the
            // first minute rather than only after somebody registers direct.
            $orphan = Customer::query()->assigned()->inRandomOrder()->first();
            $property = $stock[5];

            if ($orphan && $property) {
                $orphan->forceFill(['agent_id' => null, 'assigned_at' => null])->save();

                Deal::create([
                    'tenant_id' => $this->tenant->id,
                    'customer_id' => $orphan->id,
                    'property_id' => $property->id,
                    'agent_id' => null,
                    'value' => $property->price,
                    'commission' => 0,
                    'duration_months' => $this->r->pick($property->durationOptions()),
                    'status' => 'pending',
                    'won' => false,
                    'opened_at' => now()->subDays(2),
                    'committed_at' => now()->subDays(2),
                    'location' => $property->location,
                    'property_type' => $property->type,
                ]);

                $property->forceFill(['status' => 'reserved'])->save();
                $made++;
            }
        });

        return $made;
    }

    /** A handful who found the platform on their own and need a realtor. */
    private function plantUnassignedInvestors(): int
    {
        $ids = Customer::query()->assigned()
            ->where('heard_about_us', '!=', 'realtor')
            ->inRandomOrder()->limit(7)->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Customer::whereIn('id', $ids)->update([
            'agent_id' => null,
            'assigned_at' => null,
            'source' => 'direct',
        ]);

        return $ids->count();
    }

    /**
     * Something for a realtor to send.
     *
     * Written as templates rather than files: a placeholder that resolves to a
     * real name and a real link demonstrates the mechanism, where a stock JPEG
     * would only demonstrate that a download works.
     */
    private function makeMaterials(): int
    {
        $templates = [
            [
                'title' => 'First contact — WhatsApp',
                'kind' => 'copy',
                'body' => "Hello 👋\n\n"
                    ."I'm {first_name}, a realtor with NexABI. We help people own property "
                    ."in Lagos by paying in instalments over a term you choose — not a lump sum.\n\n"
                    ."You can see everything available here, with the monthly figure on each one:\n"
                    ."{link}\n\n"
                    ."If anything catches your eye, call me on {phone} and I'll walk you through it.\n\n"
                    .'— {realtor}',
            ],
            [
                'title' => 'Follow-up — after a viewing',
                'kind' => 'copy',
                'body' => "Hi,\n\n"
                    ."Lovely to show you around today. When you're ready, you can register here "
                    ."and pick your own payment term:\n{link}\n\n"
                    ."Nothing is binding when you commit — the unit is just held for you while "
                    ."we confirm the details, and you'll see the full schedule before anything starts.\n\n"
                    ."Any questions at all, I'm on {phone}.\n\n"
                    .'— {realtor}',
            ],
            [
                'title' => 'Instagram caption',
                'kind' => 'copy',
                'body' => "Own it in instalments, not in one payment. 🏠\n\n"
                    ."Verified properties across Lagos. You choose the term, you see every "
                    ."payment as it lands.\n\n"
                    ."Register through my link 👉 {link}\n"
                    ."Questions? {phone}\n\n"
                    .'#LagosRealEstate #PropertyNigeria #OwnYourHome',
            ],
            [
                'title' => 'Referral card — print',
                'kind' => 'flyer',
                'body' => "{realtor}\nNexABI Realtor\n\n"
                    ."Phone: {phone}\nCode: {code}\n\n"
                    ."Register: {link}\n\n"
                    .'Property ownership in instalments you choose.',
            ],
            [
                'title' => 'Cold email — professionals',
                'kind' => 'copy',
                'body' => "Subject: Owning property without the lump sum\n\n"
                    ."Hello,\n\n"
                    ."Most people who want property in Lagos are not short of intent — they are "
                    ."short of a way to pay for it that fits how they actually earn.\n\n"
                    ."NexABI lets you commit to a verified property and pay over a term you pick. "
                    ."Every instalment is recorded and visible to you, so you always know exactly "
                    ."how much of it you own.\n\n"
                    ."Have a look: {link}\n\n"
                    ."Happy to talk it through — {phone}.\n\n"
                    .'{realtor}',
            ],
        ];

        foreach ($templates as $t) {
            PromotionalMaterial::updateOrCreate(
                ['tenant_id' => $this->tenant->id, 'title' => $t['title']],
                $t + ['active' => true, 'downloads' => $this->r->int(3, 90)],
            );
        }

        return count($templates);
    }

    /**
     * Attach existing feedback to the journey moment that would have asked for it.
     *
     * Feedback lost its screen in the pivot but not its purpose — two detectors
     * run on satisfaction. Stamping the trigger is what makes the historical
     * rows consistent with how new ones will arrive.
     */
    private function stampFeedbackTriggers(): int
    {
        $n = 0;

        Feedback::query()->whereNull('trigger')->chunkById(500, function ($rows) use (&$n) {
            foreach ($rows as $row) {
                $deal = Deal::where('customer_id', $row->customer_id)
                    ->whereIn('status', array_merge(Deal::LIVE, ['completed']))
                    ->orderByDesc('value')->first();

                $row->forceFill([
                    'deal_id' => $deal?->id,
                    'trigger' => $deal?->status === 'completed' ? 'completed' : 'journey_started',
                ])->save();

                $n++;
            }
        });

        return $n;
    }
}
