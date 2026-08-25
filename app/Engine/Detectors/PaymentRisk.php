<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Overdue receivables, weighted by who owes them.
 *
 * A top-ten customer 90 days late is a relationship problem, not a
 * collections one, and the narrative says so.
 */
class PaymentRisk implements Detector
{
    public function key(): string { return 'paymentRisk'; }

    public function archetype(): string { return 'threshold'; }

    public function area(): string { return 'finance'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $f = $m->finance();

        if (! $f['overdueCount']) {
            return [];
        }

        $share = Stats::safeDiv($f['overdueAmount'], $f['collected'] + $f['outstanding']);
        if ($f['overdueAmount'] < 1000000 || $share < 0.015) {
            return [];
        }

        $top = $f['byCustomer'][0] ?? null;
        $vips = array_values(array_filter($f['byCustomer'],
            fn ($c) => in_array($c['segment'], ['vip', 'high_value'], true)));

        $accounts = count($f['byCustomer']);

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'all',
            title: $accounts.' '.($accounts === 1 ? 'account is' : 'accounts are')
                .' carrying overdue instalments',
            severity: Stats::severity($share * 100, 2, 14),
            confidence: 0.95,
            facts: [
                'amount' => $f['overdueAmount'], 'accounts' => $accounts,
                'instalments' => $f['overdueCount'],
                'topName' => $top['name'] ?? '', 'topAmount' => $top['amount'] ?? 0,
                'topDays' => $top['maxOverdue'] ?? 0, 'topRank' => $top['rank'] ?? 0,
                'vipCount' => count($vips), 'vipIds' => array_column($vips, 'id'),
                'share' => $share, 'over90' => $f['aging']['90+'],
                'collectionRatio' => $f['collectionRatio'],
            ],
            evidence: [
                Signal::evidence('Overdue', $f['overdueAmount'], 'money',
                    ['route' => 'finance', 'params' => ['filter' => 'overdue']]),
                Signal::evidence('Accounts', $accounts, null,
                    ['route' => 'finance', 'params' => ['filter' => 'overdue']]),
                Signal::evidence('Largest', $top ? $top['name'].' - '.$top['maxOverdue'].'d' : '-', null,
                    ['route' => 'finance', 'params' => ['filter' => 'overdue']]),
                Signal::evidence('90+ days', $f['aging']['90+'], 'money'),
                Signal::evidence('Collection ratio', $f['collectionRatio'], 'pct'),
            ],
        )];
    }
}
