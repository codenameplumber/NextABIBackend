<?php

namespace App\Support;

/**
 * Plain-English definitions for the jargon.
 *
 * A BI tool that assumes everyone knows what "collection ratio" or "win rate"
 * means is a BI tool that half the room quietly stops trusting. These sit on
 * the KPI tiles themselves rather than in a manual nobody opens.
 */
final class MetricHelp
{
    public const DEFS = [
        'revenue' => 'Total value of deals that closed inside the period. Counted on the closing date, not when the enquiry arrived.',
        'pipeline' => 'Combined budget of every lead still open. What could close, not what will — treat it as ceiling, not forecast.',
        'inventory' => 'Asking price of every unit currently available. Excludes reserved and sold stock.',
        'overdue' => 'Instalments whose due date has passed and which have not been paid.',
        'winRate' => 'Of the deals that reached a decision, the share that closed. Leads still in progress are excluded, so this is not lead-to-sale conversion.',
        'collectionRatio' => 'Cash received against everything that has actually fallen due. Below about 92% means collections are slipping.',
        'stale' => 'Open leads with no recorded contact for 14 days or more. The clock resets the moment anyone calls, emails or messages.',
        'cac' => 'Marketing spend divided by customers acquired. Compare it to average deal value, not to revenue.',
        'roas' => 'Attributed revenue for every naira of spend. Below 1× means the campaign costs more than it returns.',
        'dom' => 'Average days between listing a property and selling it. The median matters more than the mean here — one 400-day listing drags the average.',
        'avgDeal' => 'Average value of the deals closed in the period.',
        'cycle' => 'Average days from first enquiry to closed deal.',
        'satisfaction' => 'Mean star rating across reviews in the window, out of 5.',
        'conversion' => 'Share of leads that eventually became a closed deal.',
        'occupancy' => 'Share of listed units that are currently sold or reserved.',
    ];

    public static function get(?string $key): ?string
    {
        return $key ? (self::DEFS[$key] ?? null) : null;
    }
}
