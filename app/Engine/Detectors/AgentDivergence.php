<?php

namespace App\Engine\Detectors;

use App\Engine\Detector;
use App\Engine\Metrics;
use App\Engine\Signal;
use App\Engine\Stats;
use App\Models\Tenant;

/**
 * Two agent cohorts closing at materially different rates on similar leads.
 *
 * This is a distribution problem as much as a performance one — the lagging
 * cohort is holding pipeline that would convert better elsewhere.
 */
class AgentDivergence implements Detector
{
    public function key(): string { return 'agentDivergence'; }

    public function archetype(): string { return 'cohort'; }

    public function area(): string { return 'sales'; }

    public function run(Metrics $m, Tenant $t): array
    {
        $a = $m->agents();
        $qualified = array_values(array_filter($a['rows'], fn ($x) => $x['leads'] >= 25));

        if (count($qualified) < 6 || ! $a['baselineCloseRate']) {
            return [];
        }

        $base = $a['baselineCloseRate'];
        $laggards = array_values(array_filter($qualified, fn ($x) => $x['closeRate'] < $base * 0.62));
        $leaders = array_values(array_filter($qualified, fn ($x) => $x['closeRate'] > $base * 1.3));

        if (count($laggards) < 2) {
            return [];
        }

        $lagLeads = array_sum(array_column($laggards, 'openLeads'));
        $lagPipeline = array_sum(array_column($laggards, 'pipeline'));
        $lagRate = array_sum(array_column($laggards, 'closeRate')) / count($laggards);
        $leadRate = $leaders
            ? array_sum(array_column($leaders, 'closeRate')) / count($leaders)
            : $base;

        return [new Signal(
            detector: $this->key(),
            archetype: $this->archetype(),
            area: $this->area(),
            entityKey: 'all',
            title: count($laggards).' '.(count($laggards) === 1 ? 'agent is' : 'agents are')
                .' closing at less than half the rate of your top performers',
            severity: Stats::severity(Stats::safeDiv($leadRate, $lagRate), 1.7, 4.4),
            confidence: Stats::confidence($lagLeads, 120),
            facts: [
                'laggards' => count($laggards), 'leaders' => count($leaders),
                'lagRate' => $lagRate, 'leadRate' => $leadRate, 'gap' => $leadRate - $lagRate,
                'lagLeads' => $lagLeads, 'lagPipeline' => $lagPipeline,
                'names' => array_column($laggards, 'name'),
                'leaderNames' => array_column($leaders, 'name'),
                'laggardIds' => array_column($laggards, 'id'),
                'leaderIds' => array_column($leaders, 'id'),
                'recoverable' => $lagPipeline * ($leadRate - $lagRate),
            ],
            evidence: [
                Signal::evidence('Under-performing agents', count($laggards), null,
                    ['route' => 'sales', 'params' => ['view' => 'agents']]),
                Signal::evidence('Their close rate', $lagRate, 'pct'),
                Signal::evidence('Top-performer rate', $leadRate, 'pct'),
                Signal::evidence('Open leads held', $lagLeads, null,
                    ['route' => 'leads', 'params' => ['agents' => implode(',', array_column($laggards, 'id'))]]),
                Signal::evidence('Pipeline held', $lagPipeline, 'money'),
            ],
        )];
    }
}
