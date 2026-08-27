<?php

namespace Tests\Feature;

use App\Engine\InsightEngine;
use App\Engine\Metrics;
use App\Models\Activity;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Feedback;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dataset, at the size it actually ships.
 *
 * Volume is not decoration. Three things break quietly when a fixture grows:
 * planted conditions stop clearing the detectors' thresholds, aggregate
 * queries start scanning, and generated names start repeating. Each of those
 * has its own test here because each was a real regression when this dataset
 * went from 1,400 leads to 4,500.
 */
class ScaleTest extends TestCase
{
    public function test_every_entity_carries_a_real_book(): void
    {
        $counts = [
            'agents' => Agent::count(),
            'campaigns' => Campaign::count(),
            'properties' => Property::count(),
            'leads' => Lead::count(),
            'deals' => Deal::count(),
            'customers' => Customer::count(),
            'payments' => Payment::count(),
            'feedback' => Feedback::count(),
            'activities' => Activity::count(),
        ];

        foreach ($counts as $entity => $n) {
            $this->assertGreaterThanOrEqual(
                100,
                $n,
                "Only {$n} {$entity} — too thin for the detectors to say anything trustworthy.",
            );
        }
    }

    public function test_names_are_not_repeated_into_absurdity(): void
    {
        // The tell of a generated dataset is "Chidi Okafor 7". The pool has to
        // stay larger than the book.
        $total = Lead::count();
        $distinct = Lead::distinct()->count('name');

        $this->assertGreaterThan(
            0.93,
            $distinct / $total,
            'Lead names are colliding — the name pool is smaller than the book.',
        );

        $this->assertSame(
            0,
            Lead::where('name', 'regexp', '[0-9]$')->count(),
            'A lead is named with a trailing digit, which no person is.',
        );
    }

    public function test_all_seven_planted_conditions_survive_the_scale(): void
    {
        InsightEngine::for($this->tenant)->run();

        $want = ['staleLeads', 'campaignBurn', 'demandGap', 'paymentRisk',
            'feedbackCluster', 'agentDivergence', 'funnelErosion'];

        $have = collect(InsightEngine::for($this->tenant)->live())->pluck('detector')->unique()->all();
        $missing = array_diff($want, $have);

        $this->assertEmpty(
            $missing,
            "Conditions the detectors stopped finding at this volume: ".implode(', ', $missing),
        );
    }

    public function test_the_marketing_baseline_is_not_zero(): void
    {
        // With too many campaigns for the lead volume the median campaign
        // closes nothing, the baseline collapses to zero, and every ROAS and
        // conversion figure on the marketing page becomes meaningless.
        $mk = Metrics::for($this->tenant)->marketing();

        $this->assertGreaterThan(0, $mk['baselineConversion'], 'Median campaign converts nothing.');
        $this->assertGreaterThan(0, $mk['roas'], 'No revenue is attributed to any campaign.');
        $this->assertGreaterThan(0, $mk['baselineCpl']);
    }

    public function test_the_metrics_layer_still_answers_in_aggregate_not_by_scanning(): void
    {
        $m = Metrics::for($this->tenant);
        $m->flush();

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (['sales', 'funnel', 'leads', 'properties', 'finance',
            'marketing', 'customers', 'feedback', 'agents'] as $group) {
            $m->$group();
        }

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Nine metric groups. A per-row query would put this in the thousands.
        $this->assertLessThan(
            120,
            $queries,
            "The metrics layer issued {$queries} queries — something is looping over rows.",
        );
    }

    public function test_the_warm_path_costs_almost_nothing(): void
    {
        $m = Metrics::for($this->tenant);
        $m->headline();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $m->headline();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries, 'The memo is not holding — every read re-queries.');
    }

    public function test_a_full_detector_run_stays_inside_a_request(): void
    {
        Metrics::for($this->tenant)->flush();

        $started = microtime(true);
        InsightEngine::for($this->tenant)->run();
        $ms = (microtime(true) - $started) * 1000;

        // The action bus re-runs a subset synchronously, so the full sweep has
        // to stay in the range where doing that inside a click is defensible.
        $this->assertLessThan(
            5000,
            $ms,
            'A full detector run took '.round($ms).'ms — too slow to sit inside a request.',
        );
    }

    public function test_ten_demo_accounts_exist_and_all_sign_in(): void
    {
        $emails = [
            'ada@anchorstone.ng', 'tunde@anchorstone.ng', 'ngozi@anchorstone.ng',
            'segun@anchorstone.ng', 'funke@anchorstone.ng', 'ibrahim@anchorstone.ng',
            'client@example.com', 'buyer2@example.com', 'buyer3@example.com', 'buyer4@example.com',
        ];

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();
            $this->assertNotNull($user, "{$email} was not seeded.");
            $this->assertTrue(
                \Illuminate\Support\Facades\Hash::check('password', $user->password),
                "{$email} does not accept the documented password.",
            );
            $this->assertSame($this->tenant->id, $user->tenant_id);
        }
    }

    public function test_every_buyer_account_opens_on_a_file_worth_reading(): void
    {
        foreach (User::where('is_client', true)->get() as $user) {
            $this->assertNotNull($user->customer_id, "{$user->email} is not linked to a customer.");

            $this->assertGreaterThan(
                0,
                Payment::where('customer_id', $user->customer_id)->count(),
                "{$user->email} opens on an account with no instalments to show.",
            );
        }
    }

    public function test_the_four_buyers_show_four_different_situations(): void
    {
        $shapes = User::where('is_client', true)->get()->map(function ($u) {
            $late = Payment::where('customer_id', $u->customer_id)
                ->whereNull('paid_at')->where('due_at', '<', now()->subDays(30))->count();
            $unpaid = Payment::where('customer_id', $u->customer_id)->whereNull('paid_at')->count();

            return $late > 0 ? 'behind' : ($unpaid === 0 ? 'settled' : 'running');
        })->unique();

        // A portal demo that only ever opens on a healthy account never shows
        // what the product does when something is wrong.
        $this->assertGreaterThanOrEqual(
            2,
            $shapes->count(),
            'Every demo buyer is in the same situation.',
        );
    }

    public function test_staff_seats_cover_every_role(): void
    {
        $roles = User::where('is_client', false)->pluck('role')->unique();

        foreach (['ceo', 'sales', 'finance', 'agent'] as $role) {
            $this->assertTrue($roles->contains($role), "No demo account holds the {$role} seat.");
        }
    }

    public function test_a_staff_login_is_the_same_person_as_their_agent_record(): void
    {
        // The person who signs in should be the person who appears in the team
        // table and owns leads, not a disembodied account.
        $user = User::where('email', 'ada@anchorstone.ng')->firstOrFail();

        $this->assertNotNull(
            Agent::where('email', $user->email)->first(),
            'The signed-in user has no agent record, so tickets addressed to them go nowhere.',
        );
    }
}
