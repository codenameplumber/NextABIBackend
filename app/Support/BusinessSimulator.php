<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Generates a believable ~20 months of a Lagos agency for one tenant.
 *
 * This is not "mock data". It is a seeded simulation biased so the dataset
 * genuinely *contains* the conditions the detectors will independently
 * discover. Every planted condition is declared in Vocab::NARRATIVES and is
 * visible in-app so the whole arrangement is auditable.
 *
 * Everything is anchored to "now" rather than a fixed date, so a demo opened
 * six months from today still shows a business with current activity.
 */
class BusinessSimulator
{
    private const HISTORY_DAYS = 610;
    private const DAY = 86400;

    private Rng $r;
    private int $now;
    private int $start;
    private array $usedNames = [];

    public function __construct(private Tenant $tenant, private int $seed = 20260814)
    {
        $this->r = new Rng($seed);
        $this->now = Carbon::now()->getTimestamp();
        $this->start = $this->now - self::HISTORY_DAYS * self::DAY;
    }

    public function run(): array
    {
        $agents = $this->makeAgents();
        [$campaigns, $burnId] = $this->makeCampaigns();
        $properties = $this->makeProperties();
        $leads = $this->makeLeads($agents, $campaigns, $burnId);
        [$deals, $properties, $leads] = $this->makeDeals($leads, $properties);
        [$customers, $deals] = $this->makeCustomers($deals, $leads);
        $payments = $this->makePayments($deals, $customers);
        $feedback = $this->makeFeedback($customers, $agents);
        $this->persist($agents, $campaigns, $properties, $leads, $deals, $customers, $payments, $feedback);

        return [
            'agents' => count($agents), 'campaigns' => count($campaigns),
            'properties' => count($properties), 'leads' => count($leads),
            'deals' => count($deals), 'customers' => count($customers),
            'payments' => count($payments), 'feedback' => count($feedback),
        ];
    }

    /* ------------------------------------------------------------- people */

    private function person(): string
    {
        for ($i = 0; $i < 60; $i++) {
            $n = $this->r->pick(Vocab::FIRST).' '.$this->r->pick(Vocab::LAST);
            if (! isset($this->usedNames[$n])) {
                $this->usedNames[$n] = true;

                return $n;
            }
        }

        return $this->r->pick(Vocab::FIRST).' '.$this->r->pick(Vocab::LAST).' '.$this->r->int(2, 9);
    }

    private function ts(int $unix): string
    {
        return Carbon::createFromTimestamp($unix)->toDateTimeString();
    }

    private function daysAgo(int $d): int
    {
        return $this->now - $d * self::DAY;
    }

    /* ------------------------------------------------------------- agents */

    private function makeAgents(): array
    {
        $n = Vocab::NARRATIVES['AGENT_DIVERGENCE'];
        $out = [];

        for ($i = 0; $i < 18; $i++) {
            // Cohorts are planted: 4 stars, 5 strugglers, the rest core.
            $tier = $i < $n['stars'] ? 'star'
                : ($i < $n['stars'] + $n['strugglers'] ? 'struggling' : 'core');

            $skill = match ($tier) {
                'star' => $this->r->float(0.86, 0.97),
                'struggling' => $this->r->float(0.30, 0.46),
                default => $this->r->float(0.52, 0.78),
            };

            $name = $this->person();

            $out[] = [
                'idx' => $i,
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                // Agents are people who receive things — a buyer's support
                // ticket is addressed to their own agent, not to a shared box.
                'email' => str($name)->slug('.').'@'.$this->tenant->slug.'.ng',
                'role' => $i === 0 ? 'Head of Sales' : ($i < 4 ? 'Senior Agent' : 'Sales Agent'),
                'team' => $this->r->pick(['Island', 'Mainland', 'Luxury', 'Investment']),
                'tier' => $tier,
                'skill' => round($skill, 3),
                'active' => true,
                'coaching' => false,
                'joined_at' => $this->ts($this->r->between($this->daysAgo(1500), $this->daysAgo(200))),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------- campaigns */

    private function makeCampaigns(): array
    {
        $out = [];
        $monthStart = Carbon::createFromTimestamp($this->start)->startOfMonth();
        $end = Carbon::createFromTimestamp($this->now);

        $paidChannels = ['Social', 'Paid Search', 'Marketplace', 'Offline'];
        $paidSources = array_values(array_filter(
            Vocab::SOURCES,
            fn ($s) => in_array($s['channel'], $paidChannels, true),
        ));

        $idx = 0;
        for ($m = $monthStart->copy(); $m->lte($end); $m->addMonth()) {
            $perMonth = $this->r->int(1, 3);
            for ($k = 0; $k < $perMonth; $k++) {
                $src = $this->r->fromTable(array_map(fn ($s) => ['v' => $s, 'w' => $s['w']], $paidSources));
                $loc = $this->r->fromTable(array_map(fn ($l) => ['v' => $l, 'w' => $l['w']], Vocab::LOCATIONS));

                $startAt = $m->getTimestamp() + $this->r->int(0, 6) * self::DAY;
                $duration = $this->r->int(21, 62);
                // Sized so total paid spend lands near 1.5% of revenue and
                // blended ROAS sits in the 2-3x band.
                $spend = round($this->r->logNormal(7200000, 0.5) / 100000) * 100000;
                $impressions = (int) round($spend * $this->r->float(2.2, 4.6));

                $out[] = [
                    'idx' => $idx++,
                    'tenant_id' => $this->tenant->id,
                    'name' => $src['name'].' - '.$loc['name'].' '
                        .['Push', 'Launch', 'Retarget', 'Awareness', 'Lead Gen'][$k % 5],
                    'channel' => $src['channel'],
                    'source' => $src['name'],
                    'location' => $loc['name'],
                    'spend' => $spend,
                    'impressions' => $impressions,
                    'clicks' => (int) round($impressions * $this->r->float(0.008, 0.031)),
                    'start_at' => $this->ts($startAt),
                    'end_at' => $this->ts($startAt + $duration * self::DAY),
                    'start_ts' => $startAt,
                    'end_ts' => $startAt + $duration * self::DAY,
                    'status' => ($startAt + $duration * self::DAY) > $this->now ? 'active' : 'completed',
                    'planted' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        // PLANTED: the burn campaign. Recent, heavy spend, conversion starved.
        $n = Vocab::NARRATIVES['CAMPAIGN_BURN'];
        $avgSpend = array_sum(array_column($out, 'spend')) / max(1, count($out));
        $burnStart = $this->daysAgo(112);
        $burnSpend = round($avgSpend * $n['spendMult'] / 100000) * 100000;
        $burnImpressions = (int) round($avgSpend * $n['spendMult'] * 3.4);

        $burnIdx = $idx;
        $out[] = [
            'idx' => $burnIdx,
            'tenant_id' => $this->tenant->id,
            'name' => $n['name'],
            'channel' => 'Social',
            'source' => 'Facebook',
            'location' => 'Gbagada',
            'spend' => $burnSpend,
            'impressions' => $burnImpressions,
            'clicks' => (int) round($burnImpressions * 0.014),
            'start_at' => $this->ts($burnStart),
            'end_at' => $this->ts($burnStart + 108 * self::DAY),
            'start_ts' => $burnStart,
            'end_ts' => $burnStart + 108 * self::DAY,
            'status' => 'active',
            'planted' => 'CAMPAIGN_BURN',
            'created_at' => now(), 'updated_at' => now(),
        ];

        return [$out, $burnIdx];
    }

    /* --------------------------------------------------------- properties */

    private function makeProperties(): array
    {
        $out = [];
        $gap = Vocab::NARRATIVES['DEMAND_GAP'];

        for ($i = 0; $i < 224; $i++) {
            $loc = $this->r->fromTable(array_map(fn ($l) => ['v' => $l, 'w' => $l['w']], Vocab::LOCATIONS));
            $type = $this->r->fromTable(array_map(fn ($t) => ['v' => $t, 'w' => $t['w']], Vocab::PROP_TYPES));
            $beds = $this->r->pick($type['beds']);

            $bedMult = $beds ? 0.62 + $beds * 0.19 : 1;
            $price = round($loc['base'] * $type['mult'] * $bedMult * $this->r->float(0.78, 1.34) / 500000) * 500000;

            $listedAt = $this->r->between($this->start, $this->daysAgo(6));
            $ageDays = (int) floor(($this->now - $listedAt) / self::DAY);

            $sellP = min(0.84, ($ageDays / 360) * $loc['demand'] * $this->r->float(0.7, 1.3));
            $status = 'available';
            $soldAt = null;

            if ($this->r->chance($sellP)) {
                $status = 'sold';
                $soldAt = $listedAt + (int) round($this->r->logNormal(78 / $loc['demand'], 0.5)) * self::DAY;
                if ($soldAt > $this->now) {
                    $status = $this->r->chance(0.4) ? 'reserved' : 'available';
                    $soldAt = null;
                }
            } elseif ($this->r->chance(0.07)) {
                $status = 'reserved';
            } elseif ($this->r->chance(0.04)) {
                $status = 'off_market';
            }

            $views = (int) round($this->r->logNormal(240 * $loc['demand'], 0.62));

            $out[] = [
                'idx' => $i,
                'tenant_id' => $this->tenant->id,
                'ref' => 'NX-'.(1000 + $i),
                'title' => $beds ? $beds.'-Bedroom '.$type['name'] : $type['name'],
                'type' => $type['name'],
                'location' => $loc['name'],
                'tier' => $loc['tier'],
                'bedrooms' => $beds,
                'size_sqm' => $beds ? $beds * $this->r->int(48, 86) : $this->r->int(300, 1400),
                'price' => $price,
                'status' => $status,
                'listed_at' => $this->ts($listedAt),
                'listed_ts' => $listedAt,
                'sold_at' => $soldAt ? $this->ts($soldAt) : null,
                'sold_ts' => $soldAt,
                'days_on_market' => $soldAt ? (int) round(($soldAt - $listedAt) / self::DAY) : $ageDays,
                'views' => $views,
                'inquiries' => max(0, (int) round($views * $this->r->float(0.03, 0.09))),
                'agent_idx' => $this->r->int(0, 17),
                'planted' => null,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        // PLANTED: demand/supply gap. Constrain stock, inflate inquiries.
        $segment = array_keys(array_filter($out, fn ($p) => $p['location'] === $gap['location']
            && $p['type'] === $gap['type'] && $p['bedrooms'] === $gap['beds']));

        $availableIdx = array_values(array_filter($segment, fn ($k) => $out[$k]['status'] === 'available'));
        $keep = array_flip($this->r->sample($availableIdx, $gap['stockCap']));

        // Release sold units back if the natural draw left too few, so the
        // planted squeeze is the stated size rather than whatever the RNG gave.
        if (count($availableIdx) < $gap['stockCap']) {
            $soldIdx = array_values(array_filter($segment, fn ($k) => $out[$k]['status'] === 'sold'));
            foreach ($this->r->sample($soldIdx, $gap['stockCap'] - count($availableIdx)) as $k) {
                $out[$k]['status'] = 'available';
                $out[$k]['sold_at'] = null;
                $out[$k]['sold_ts'] = null;
                $out[$k]['days_on_market'] = (int) floor(($this->now - $out[$k]['listed_ts']) / self::DAY);
                $keep[$k] = true;
            }
        }

        foreach ($segment as $k) {
            $out[$k]['planted'] = 'DEMAND_GAP';
            $out[$k]['inquiries'] = (int) round($out[$k]['inquiries'] * $gap['inquiryMult']);
            $out[$k]['views'] = (int) round($out[$k]['views'] * $gap['inquiryMult'] * 0.7);

            if ($out[$k]['status'] === 'available' && ! isset($keep[$k])) {
                $out[$k]['status'] = 'sold';
                $soldAt = $out[$k]['sold_ts'] ?: $this->r->between($this->daysAgo(120), $this->daysAgo(5));
                $out[$k]['sold_at'] = $this->ts($soldAt);
                $out[$k]['sold_ts'] = $soldAt;
                $out[$k]['days_on_market'] = (int) round(($soldAt - $out[$k]['listed_ts']) / self::DAY);
            }
        }

        return $out;
    }

    /* -------------------------------------------------------------- leads */

    private function makeLeads(array $agents, array $campaigns, int $burnIdx): array
    {
        $out = [];
        $stages = Vocab::STAGES;

        for ($i = 0; $i < 1420; $i++) {
            $createdAt = $this->r->between($this->start, $this->now - 2 * self::DAY);
            $season = Vocab::seasonality($createdAt);

            $src = $this->r->fromTable(array_map(
                fn ($s) => ['v' => $s, 'w' => $s['w'] * $season], Vocab::SOURCES));

            // Attach to a campaign that was live when the lead arrived.
            $live = array_values(array_filter($campaigns, fn ($c) => $createdAt >= $c['start_ts']
                && $createdAt <= $c['end_ts'] && $c['source'] === $src['name']));
            $campaignIdx = ($live && $this->r->chance(0.72)) ? $this->r->pick($live)['idx'] : null;

            $loc = $this->r->fromTable(array_map(fn ($l) => ['v' => $l, 'w' => $l['w']], Vocab::LOCATIONS));
            $type = $this->r->fromTable(array_map(fn ($t) => ['v' => $t, 'w' => $t['w']], Vocab::PROP_TYPES));
            $budget = round($loc['base'] * $type['mult'] * $this->r->float(0.7, 1.5) / 1000000) * 1000000;

            $agent = $agents[$this->r->int(0, count($agents) - 1)];
            $ageDays = (int) floor(($this->now - $createdAt) / self::DAY);

            // Progression: source quality x agent skill x time.
            $drive = $src['quality'] * (0.55 + $agent['skill'] * 0.9) * min(1, $ageDays / 45);
            $depth = 0;
            foreach ([0.88, 0.72, 0.58, 0.47, 0.41] as $s => $stepP) {
                if ($this->r->chance(min(0.94, $stepP * $drive * 0.82))) {
                    $depth = $s + 1;
                } else {
                    break;
                }
            }

            $status = null;
            $convertedAt = null;
            $lostReason = null;

            if ($depth === 5 && $this->r->chance(0.55 + $agent['skill'] * 0.3)) {
                $status = 'won';
                $convertedAt = $createdAt + $this->r->int(18, 130) * self::DAY;
                if ($convertedAt > $this->now) {
                    $status = 'negotiating';
                    $convertedAt = null;
                }
            } else {
                // Leads resolve with age. A real pipeline does not carry
                // 400-day-old "open" leads, and pretending it does would make
                // the stagnation detector measure nothing but the generator.
                $decay = $ageDays < 45 ? 0.06
                    : ($ageDays < 90 ? 0.34
                    : ($ageDays < 150 ? 0.66
                    : ($ageDays < 240 ? 0.86 : 0.96)));

                if ($this->r->chance($decay)) {
                    $status = 'lost';
                    $lostReason = $this->r->pick(Vocab::LOST_REASONS);
                } else {
                    $status = $stages[$depth];
                }
            }

            $score = (int) round(min(99, max(4,
                28 + $src['quality'] * 19 + $depth * 8 + $this->r->gauss(0, 9) + ($budget > 200000000 ? 7 : 0))));

            $open = ! in_array($status, ['won', 'lost'], true);

            // Open leads are actively worked, so their last touch is recent.
            // Staleness must be a planted or action-induced condition, never an
            // artefact of the generator.
            $lastContact = $open
                ? max($createdAt, $this->now - $this->r->int(0, 11) * self::DAY)
                : min($this->now, $createdAt + (int) round($ageDays * $this->r->float(0.3, 0.95)) * self::DAY);

            $out[] = [
                'idx' => $i,
                'tenant_id' => $this->tenant->id,
                'name' => $this->person(),
                'source' => $src['name'],
                'channel' => $src['channel'],
                'campaign_idx' => $campaignIdx,
                'status' => $status,
                'max_stage' => $status === 'won' ? 'won' : $stages[$depth],
                'depth' => $depth,
                'score' => $score,
                'budget' => $budget,
                'wants_location' => $loc['name'],
                'wants_type' => $type['name'],
                'agent_idx' => $agent['idx'],
                'created_ts' => $createdAt,
                'last_contact_ts' => $lastContact,
                'converted_ts' => $convertedAt,
                'lost_reason' => $lostReason,
                'is_open' => $open,
                'planted' => null,
            ];
        }

        foreach ($out as $i => $l) {
            $out[$i]['email'] = strtolower(preg_replace('/[^a-z]+/i', '.', $l['name']))
                .'@'.$this->r->pick(['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com']);
            $out[$i]['phone'] = '+2348'.$this->r->int(10, 99).$this->r->int(1000000, 9999999);
        }

        $out = $this->plantStaleLeads($out);
        $out = $this->plantCampaignBurn($out, $campaigns, $burnIdx);
        $out = $this->plantFunnelErosion($out);

        return $out;
    }

    /** PLANTED: stale leads. Freeze the contact date on open, high-budget leads. */
    private function plantStaleLeads(array $leads): array
    {
        $n = Vocab::NARRATIVES['STALE_LEADS'];

        $candidates = array_keys(array_filter($leads, fn ($l) => $l['is_open']
            && $l['status'] !== 'new' && $l['budget'] > 80000000));

        usort($candidates, fn ($a, $b) => $leads[$b]['budget'] <=> $leads[$a]['budget']);
        $candidates = array_slice($candidates, 0, 180);

        foreach ($this->r->sample($candidates, $n['count']) as $k) {
            $leads[$k]['last_contact_ts'] = $this->daysAgo($this->r->int($n['minDays'], $n['maxDays']));
            $leads[$k]['planted'] = 'STALE_LEADS';
            // Stale *and* valuable — that is the entire point.
            if ($leads[$k]['score'] < 55) {
                $leads[$k]['score'] = $this->r->int(55, 84);
            }
        }

        return $leads;
    }

    /** PLANTED: heavy spend buys volume, then starve it of conversions. */
    private function plantCampaignBurn(array $leads, array $campaigns, int $burnIdx): array
    {
        $burn = null;
        foreach ($campaigns as $c) {
            if ($c['idx'] === $burnIdx) {
                $burn = $c;
                break;
            }
        }
        if (! $burn) {
            return $leads;
        }

        $n = Vocab::NARRATIVES['CAMPAIGN_BURN'];

        // Volume matters: a campaign with a dozen leads is correctly ignored by
        // the detector on sample size, so the planted condition has to clear
        // that bar honestly rather than around it.
        foreach ($leads as $i => $l) {
            if ($l['source'] === 'Facebook'
                && $l['created_ts'] >= $burn['start_ts']
                && $l['created_ts'] <= min($burn['end_ts'], $this->now)
                && $this->r->chance(0.85)) {
                $leads[$i]['campaign_idx'] = $burnIdx;
            }
        }

        foreach ($leads as $i => $l) {
            if ($l['campaign_idx'] !== $burnIdx) {
                continue;
            }
            if ($l['status'] === 'won' && ! $this->r->chance($n['convMult'])) {
                $leads[$i]['status'] = $this->r->chance(0.5) ? 'lost' : 'qualified';
                $leads[$i]['converted_ts'] = null;
                $leads[$i]['lost_reason'] = $leads[$i]['status'] === 'lost' ? 'Went cold' : null;
                $leads[$i]['is_open'] = $leads[$i]['status'] !== 'lost';
            }
            $leads[$i]['planted'] = 'CAMPAIGN_BURN';
        }

        return $leads;
    }

    /** PLANTED: funnel erosion. Demote recent leads that would have advanced. */
    private function plantFunnelErosion(array $leads): array
    {
        $n = Vocab::NARRATIVES['FUNNEL_EROSION'];
        $cut = $this->daysAgo($n['windowWeeks'] * 7);

        foreach ($leads as $i => $l) {
            if ($l['created_ts'] <= $cut) {
                continue;
            }
            if (! in_array($l['max_stage'], ['negotiating', 'won'], true)) {
                continue;
            }
            if ($this->r->chance(1 - $n['dropTo'] / $n['baseline'])) {
                $leads[$i]['status'] = 'viewing';
                $leads[$i]['max_stage'] = 'viewing';   // they genuinely never got past the visit
                $leads[$i]['depth'] = 3;
                $leads[$i]['converted_ts'] = null;
                $leads[$i]['is_open'] = true;
                $leads[$i]['planted'] = $leads[$i]['planted'] ?: 'FUNNEL_EROSION';
            }
        }

        return $leads;
    }

    /* -------------------------------------------------------------- deals */

    private function makeDeals(array $leads, array $properties): array
    {
        $deals = [];
        $taken = [];
        $soldKeys = array_keys(array_filter($properties, fn ($p) => $p['status'] === 'sold'));

        foreach ($leads as $i => $l) {
            if ($l['status'] !== 'won' || ! $l['converted_ts']) {
                continue;
            }

            // Match a sold property near the budget, preferring the target area.
            $wanted = array_values(array_filter($soldKeys, fn ($k) => ! isset($taken[$k])
                && $properties[$k]['location'] === $l['wants_location']
                && abs($properties[$k]['price'] - $l['budget']) / max(1, $l['budget']) < 0.45));

            $any = array_values(array_filter($soldKeys, fn ($k) => ! isset($taken[$k])
                && abs($properties[$k]['price'] - $l['budget']) / max(1, $l['budget']) < 0.6));

            $k = $wanted ? $this->r->pick($wanted) : ($any ? $this->r->pick($any) : null);
            if ($k === null) {
                continue;
            }

            $taken[$k] = true;
            $leads[$i]['property_idx'] = $k;
            $p = $properties[$k];

            $value = round($p['price'] * $this->r->float(0.93, 1.02) / 500000) * 500000;

            $deals[] = [
                'idx' => count($deals),
                'tenant_id' => $this->tenant->id,
                'lead_idx' => $i,
                'property_idx' => $k,
                'agent_idx' => $l['agent_idx'],
                'campaign_idx' => $l['campaign_idx'],
                'value' => $value,
                'commission' => round($value * $this->r->float(0.021, 0.052)),
                'opened_ts' => $l['created_ts'],
                'closed_ts' => $l['converted_ts'],
                'cycle_days' => (int) round(($l['converted_ts'] - $l['created_ts']) / self::DAY),
                'source' => $l['source'],
                'location' => $p['location'],
                'property_type' => $p['type'],
                'won' => true,
                'lost_reason' => null,
                'customer_idx' => null,
            ];
        }

        // Lost deals, so conversion rates and funnel maths stay honest.
        foreach ($leads as $i => $l) {
            if ($l['status'] !== 'lost' || $l['score'] <= 45 || ! $this->r->chance(0.55)) {
                continue;
            }

            $deals[] = [
                'idx' => count($deals),
                'tenant_id' => $this->tenant->id,
                'lead_idx' => $i,
                'property_idx' => null,
                'agent_idx' => $l['agent_idx'],
                'campaign_idx' => $l['campaign_idx'],
                'value' => $l['budget'],
                'commission' => 0,
                'opened_ts' => $l['created_ts'],
                'closed_ts' => min($this->now, $l['created_ts'] + $this->r->int(25, 150) * self::DAY),
                'cycle_days' => 0,
                'source' => $l['source'],
                'location' => $l['wants_location'],
                'property_type' => $l['wants_type'],
                'won' => false,
                'lost_reason' => $l['lost_reason'],
                'customer_idx' => null,
            ];
        }

        return [$deals, $properties, $leads];
    }

    /* ---------------------------------------------------------- customers */

    private function makeCustomers(array $deals, array $leads): array
    {
        $customers = [];
        $byLead = [];

        foreach ($deals as $i => $d) {
            if (! $d['won']) {
                continue;
            }
            $l = $leads[$d['lead_idx']];

            if (! isset($byLead[$d['lead_idx']])) {
                $byLead[$d['lead_idx']] = count($customers);
                $customers[] = [
                    'idx' => count($customers),
                    'tenant_id' => $this->tenant->id,
                    'name' => $l['name'],
                    'email' => $l['email'],
                    'phone' => $l['phone'],
                    'agent_idx' => $d['agent_idx'],
                    'since_ts' => $d['closed_ts'],
                    'ltv' => 0,
                    'segment' => 'standard',
                    'satisfaction' => null,
                    'source' => $l['source'],
                ];
            }

            $ci = $byLead[$d['lead_idx']];
            $customers[$ci]['ltv'] += $d['value'];
            $deals[$i]['customer_idx'] = $ci;
        }

        // Rank and segment by lifetime value.
        $order = array_keys($customers);
        usort($order, fn ($a, $b) => $customers[$b]['ltv'] <=> $customers[$a]['ltv']);
        $total = count($order);

        foreach ($order as $rank => $ci) {
            $customers[$ci]['rank'] = $rank + 1;
            $customers[$ci]['segment'] = $rank < $total * 0.08 ? 'vip'
                : ($rank < $total * 0.3 ? 'high_value'
                : ($rank < $total * 0.75 ? 'standard' : 'entry'));
        }

        $customers['_order'] = $order;

        return [$customers, $deals];
    }

    /* ----------------------------------------------------------- payments */

    private function makePayments(array $deals, array $customers): array
    {
        $order = $customers['_order'];
        unset($customers['_order']);

        $payments = [];

        foreach ($deals as $d) {
            if (! $d['won'] || $d['customer_idx'] === null) {
                continue;
            }

            $plan = $this->r->weighted([1, 2, 3, 4, 6], [30, 22, 20, 16, 12]);
            $each = round($d['value'] / $plan / 10000) * 10000;

            for ($n = 0; $n < $plan; $n++) {
                $dueAt = $d['closed_ts'] + $n * $this->r->int(28, 45) * self::DAY;
                $isFuture = $dueAt > $this->now;
                $status = 'scheduled';
                $paidAt = null;

                if (! $isFuture) {
                    $overdueDays = (int) floor(($this->now - $dueAt) / self::DAY);
                    // A healthy book clears ~97% of what falls due. The overdue
                    // population must stay small enough that the planted
                    // accounts dominate it, or the finance detector is just
                    // describing background noise.
                    if ($this->r->chance(0.978 - min(0.05, $overdueDays / 5000))) {
                        $status = 'paid';
                        $paidAt = min($this->now - self::DAY, $dueAt + $this->r->int(-4, 12) * self::DAY);
                    } elseif ($overdueDays > 30) {
                        $status = 'overdue';
                    } else {
                        $status = $this->r->chance(0.4) ? 'partial' : 'pending';
                    }
                }

                $payments[] = [
                    'tenant_id' => $this->tenant->id,
                    'deal_idx' => $d['idx'],
                    'customer_idx' => $d['customer_idx'],
                    'agent_idx' => $d['agent_idx'],
                    'amount' => $n === $plan - 1 ? $d['value'] - $each * ($plan - 1) : $each,
                    'instalment' => $n + 1,
                    'of_total' => $plan,
                    'due_ts' => $dueAt,
                    'paid_ts' => $paidAt,
                    'status' => $status,
                    'method' => $this->r->pick(['Bank Transfer', 'Cheque', 'Card', 'Mortgage Drawdown']),
                    'planted' => null,
                ];
            }
        }

        // PLANTED: payment risk. Force a defined set overdue, one of them a whale.
        $n = Vocab::NARRATIVES['PAYMENT_RISK'];
        $whale = $order[$n['whaleIndex']] ?? null;
        $pool = array_slice($order, 1, (int) floor(count($order) * 0.4));
        $risk = array_values(array_filter(array_merge(
            [$whale],
            $this->r->sample($pool, $n['accounts'] - 1),
        ), fn ($x) => $x !== null));

        foreach ($risk as $ci) {
            $theirs = array_keys(array_filter($payments, fn ($p) => $p['customer_idx'] === $ci
                && $p['status'] !== 'paid' && $p['due_ts'] <= $this->now));

            if (! $theirs) {
                $theirs = array_slice(array_keys(array_filter(
                    $payments, fn ($p) => $p['customer_idx'] === $ci)), -1);
            }

            foreach (array_slice($theirs, 0, 2) as $k) {
                $due = $this->daysAgo($this->r->int($n['minOverdueDays'], $n['minOverdueDays'] + 58));
                $payments[$k]['status'] = 'overdue';
                $payments[$k]['paid_ts'] = null;
                $payments[$k]['due_ts'] = $due;
                $payments[$k]['planted'] = 'PAYMENT_RISK';
            }
        }

        return $payments;
    }

    /* ----------------------------------------------------------- feedback */

    private function makeFeedback(array $customers, array $agents): array
    {
        unset($customers['_order']);
        $out = [];

        foreach ($customers as $c) {
            $count = $this->r->weighted([0, 1, 2], [22, 62, 16]);
            for ($k = 0; $k < $count; $k++) {
                $skill = $agents[$c['agent_idx']]['skill'];
                $good = $this->r->chance(0.32 + $skill * 0.45);
                $rating = $good ? $this->r->int(4, 5) : ($this->r->chance(0.45) ? 3 : $this->r->int(1, 2));

                $body = $rating >= 4 ? $this->r->pick(Vocab::POSITIVE_FB)
                    : ($rating === 3 ? $this->r->pick(Vocab::NEUTRAL_FB) : $this->r->pick(Vocab::NEGATIVE_FB));

                $out[] = [
                    'tenant_id' => $this->tenant->id,
                    'customer_idx' => $c['idx'],
                    'agent_idx' => $c['agent_idx'],
                    'rating' => $rating,
                    'sentiment' => round(($rating - 3) / 2, 2),
                    'body' => $body,
                    'tags' => json_encode($this->r->sample(Vocab::FEEDBACK_TAGS, $this->r->int(1, 2))),
                    'channel' => $this->r->pick(['Post-sale survey', 'Google Review', 'WhatsApp', 'Email', 'Phone']),
                    'created_ts' => $this->r->between($c['since_ts'], $this->now),
                    'planted' => null,
                ];
            }
        }

        // PLANTED: complaint cluster concentrated in the trailing window.
        $n = Vocab::NARRATIVES['FEEDBACK_CLUSTER'];
        $slow = array_values(array_filter(Vocab::NEGATIVE_FB,
            fn ($t) => preg_match('/respon|callback|chase|picked up|dropped off|week/i', $t)));

        $keys = array_keys($customers);
        for ($k = 0; $k < $n['count']; $k++) {
            $c = $customers[$this->r->pick($keys)];
            $rating = $this->r->weighted([1, 2, 3], [34, 46, 20]);

            $out[] = [
                'tenant_id' => $this->tenant->id,
                'customer_idx' => $c['idx'],
                'agent_idx' => $c['agent_idx'],
                'rating' => $rating,
                'sentiment' => round(($rating - 3) / 2, 2),
                'body' => $this->r->pick($slow),
                'tags' => json_encode([$n['tag'], $this->r->pick(['communication', 'after-sales', 'site visit'])]),
                'channel' => $this->r->pick(['Google Review', 'WhatsApp', 'Post-sale survey']),
                'created_ts' => $this->r->between($this->daysAgo($n['windowDays']), $this->now - self::DAY),
                'planted' => 'FEEDBACK_CLUSTER',
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------ persist */

    private function persist(
        array $agents, array $campaigns, array $properties, array $leads,
        array $deals, array $customers, array $payments, array $feedback,
    ): void {
        $order = $customers['_order'] ?? [];
        unset($customers['_order']);

        DB::transaction(function () use (
            $agents, $campaigns, $properties, $leads, $deals, $customers, $payments, $feedback
        ) {
            $agentIds = $this->insertMapped('agents', $agents, ['idx', 'skill'], fn ($a) => [
                'skill' => $a['skill'],
            ]);

            $campaignIds = $this->insertMapped('campaigns', $campaigns,
                ['idx', 'start_ts', 'end_ts'], fn ($c) => []);

            $propIds = $this->insertMapped('properties', $properties,
                ['idx', 'listed_ts', 'sold_ts', 'agent_idx'],
                fn ($p) => ['agent_id' => $agentIds[$p['agent_idx']] ?? null]);

            // Customers first so leads and deals can reference them.
            $custIds = $this->insertMapped('customers', $customers,
                ['idx', 'since_ts', 'agent_idx'], fn ($c) => [
                    'agent_id' => $agentIds[$c['agent_idx']] ?? null,
                    'since' => $this->ts($c['since_ts']),
                ]);

            $leadIds = $this->insertMapped('leads', $leads,
                ['idx', 'created_ts', 'last_contact_ts', 'converted_ts', 'agent_idx',
                    'campaign_idx', 'property_idx', 'depth'],
                fn ($l) => [
                    'assigned_agent_id' => $agentIds[$l['agent_idx']] ?? null,
                    'campaign_id' => $l['campaign_idx'] !== null ? ($campaignIds[$l['campaign_idx']] ?? null) : null,
                    'property_id' => isset($l['property_idx']) ? ($propIds[$l['property_idx']] ?? null) : null,
                    'created_at' => $this->ts($l['created_ts']),
                    'updated_at' => $this->ts($l['created_ts']),
                    'last_contact_at' => $this->ts($l['last_contact_ts']),
                    'converted_at' => $l['converted_ts'] ? $this->ts($l['converted_ts']) : null,
                ]);

            $dealIds = $this->insertMapped('deals', $deals,
                ['idx', 'opened_ts', 'closed_ts', 'agent_idx', 'campaign_idx',
                    'property_idx', 'lead_idx', 'customer_idx'],
                fn ($d) => [
                    'agent_id' => $agentIds[$d['agent_idx']] ?? null,
                    'campaign_id' => $d['campaign_idx'] !== null ? ($campaignIds[$d['campaign_idx']] ?? null) : null,
                    'property_id' => $d['property_idx'] !== null ? ($propIds[$d['property_idx']] ?? null) : null,
                    'lead_id' => $leadIds[$d['lead_idx']] ?? null,
                    'customer_id' => $d['customer_idx'] !== null ? ($custIds[$d['customer_idx']] ?? null) : null,
                    'opened_at' => $this->ts($d['opened_ts']),
                    'closed_at' => $this->ts($d['closed_ts']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            $this->insertChunked('payments', array_map(fn ($p) => [
                'tenant_id' => $p['tenant_id'],
                'deal_id' => $dealIds[$p['deal_idx']] ?? null,
                'customer_id' => $custIds[$p['customer_idx']] ?? null,
                'agent_id' => $agentIds[$p['agent_idx']] ?? null,
                'amount' => $p['amount'],
                'instalment' => $p['instalment'],
                'of_total' => $p['of_total'],
                'due_at' => $this->ts($p['due_ts']),
                'paid_at' => $p['paid_ts'] ? $this->ts($p['paid_ts']) : null,
                'status' => $p['status'],
                'method' => $p['method'],
                'planted' => $p['planted'],
                'created_at' => now(), 'updated_at' => now(),
            ], $payments));

            $this->insertChunked('feedbacks', array_map(fn ($f) => [
                'tenant_id' => $f['tenant_id'],
                'customer_id' => $custIds[$f['customer_idx']] ?? null,
                'agent_id' => $agentIds[$f['agent_idx']] ?? null,
                'rating' => $f['rating'],
                'sentiment' => $f['sentiment'],
                'body' => $f['body'],
                'tags' => $f['tags'],
                'channel' => $f['channel'],
                'planted' => $f['planted'],
                'created_at' => $this->ts($f['created_ts']),
                'updated_at' => $this->ts($f['created_ts']),
            ], $feedback));

            $this->makeTransitionsAndActivities($leads, $leadIds, $agentIds);
            $this->rollUpSatisfaction();
        });
    }

    /**
     * The activity spine and the stage history, derived from the leads
     * themselves so the timeline and the entities can never disagree.
     */
    private function makeTransitionsAndActivities(array $leads, array $leadIds, array $agentIds): void
    {
        $transitions = [];
        $activities = [];
        $stages = Vocab::STAGES;

        foreach ($leads as $l) {
            $leadId = $leadIds[$l['idx']] ?? null;
            if (! $leadId) {
                continue;
            }

            $agentId = $agentIds[$l['agent_idx']] ?? null;
            $span = max(1, $l['last_contact_ts'] - $l['created_ts']);

            // Walk the lead through every stage it actually reached.
            $depth = $l['depth'];
            $prev = null;
            for ($s = 0; $s <= $depth; $s++) {
                $at = $l['created_ts'] + (int) ($span * ($s / max(1, $depth + 1)));
                $transitions[] = [
                    'tenant_id' => $this->tenant->id,
                    'lead_id' => $leadId,
                    'from_stage' => $prev,
                    'to_stage' => $stages[$s],
                    'at' => $this->ts($at),
                ];
                $prev = $stages[$s];
            }

            if ($l['status'] === 'won' && $l['converted_ts']) {
                $transitions[] = [
                    'tenant_id' => $this->tenant->id, 'lead_id' => $leadId,
                    'from_stage' => $prev, 'to_stage' => 'won',
                    'at' => $this->ts($l['converted_ts']),
                ];
            } elseif ($l['status'] === 'lost') {
                $transitions[] = [
                    'tenant_id' => $this->tenant->id, 'lead_id' => $leadId,
                    'from_stage' => $prev, 'to_stage' => 'lost',
                    'at' => $this->ts($l['last_contact_ts']),
                ];
            }

            $activities[] = [
                'tenant_id' => $this->tenant->id, 'type' => 'lead_created',
                'entity_type' => 'lead', 'entity_id' => $leadId, 'agent_id' => $agentId,
                'at' => $this->ts($l['created_ts']),
                'note' => 'Lead captured via '.$l['source'],
                'created_at' => now(), 'updated_at' => now(),
            ];

            $touches = min($depth + $this->r->int(0, 2), 6);
            for ($k = 0; $k < $touches; $k++) {
                $at = $l['created_ts'] + (int) ($span * (($k + 1) / ($touches + 1)));
                if ($at > $this->now) {
                    break;
                }
                $activities[] = [
                    'tenant_id' => $this->tenant->id,
                    'type' => $this->r->pick(['call', 'whatsapp', 'email', 'site_visit', 'note']),
                    'entity_type' => 'lead', 'entity_id' => $leadId, 'agent_id' => $agentId,
                    'at' => $this->ts($at), 'note' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        $this->insertChunked('lead_stage_transitions', $transitions);
        $this->insertChunked('activities', $activities);
    }

    /**
     * Roll each customer's feedback up onto the customer row.
     *
     * The detractor detector reads customers.satisfaction, so leaving it null
     * means the detector silently finds nothing — a whole area of the product
     * quietly reporting "all clear" because of a missing write-back.
     */
    private function rollUpSatisfaction(): void
    {
        DB::statement(
            'UPDATE customers c
             JOIN (
                 SELECT customer_id, AVG(rating) avg_rating
                 FROM feedbacks
                 WHERE tenant_id = ? AND customer_id IS NOT NULL
                 GROUP BY customer_id
             ) f ON f.customer_id = c.id
             SET c.satisfaction = ROUND(f.avg_rating, 2)
             WHERE c.tenant_id = ?',
            [$this->tenant->id, $this->tenant->id],
        );
    }

    /* ------------------------------------------------------------ helpers */

    /**
     * Insert rows and return idx => database id.
     *
     * MySQL guarantees consecutive auto-increment ids for a single multi-row
     * insert, but chunking breaks that guarantee, so each chunk's first id is
     * read back and the map built from it.
     */
    private function insertMapped(string $table, array $rows, array $strip, callable $extra): array
    {
        $map = [];
        $chunks = array_chunk($rows, 400, true);

        foreach ($chunks as $chunk) {
            $payload = [];
            $order = [];

            foreach ($chunk as $row) {
                $order[] = $row['idx'];
                $clean = array_diff_key($row, array_flip($strip));
                $clean = array_merge($clean, $extra($row));
                $clean['created_at'] ??= now();
                $clean['updated_at'] ??= now();
                $payload[] = $clean;
            }

            DB::table($table)->insert($payload);
            $firstId = (int) DB::getPdo()->lastInsertId();

            foreach ($order as $i => $idx) {
                $map[$idx] = $firstId + $i;
            }
        }

        return $map;
    }

    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
