<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Insight extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'facts' => 'array', 'evidence' => 'array',
        'severity' => 'float', 'confidence' => 'float', 'priority' => 'float',
        'first_seen_at' => 'datetime', 'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime', 'dismissed_at' => 'datetime',
        'actioned_at' => 'datetime',
    ];

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderByDesc('is_primary')->orderByDesc('impact');
    }

    public function primaryRecommendation(): ?Recommendation
    {
        return $this->recommendations->firstWhere(fn ($r) => $r->is_primary && ! $r->done_at)
            ?? $this->recommendations->firstWhere(fn ($r) => ! $r->done_at);
    }

    public function isLive(): bool
    {
        return $this->resolved_at === null && $this->dismissed_at === null;
    }

    /** How long this went unfixed. Only meaningful once resolved. */
    public function daysOpen(): ?int
    {
        return $this->resolved_at
            ? (int) $this->first_seen_at->diffInDays($this->resolved_at)
            : null;
    }

    /**
     * The question "Find out more" carries into Ask NexABI.
     *
     * Phrased the way the operator would ask it out loud, with this finding's
     * own figures written in — so the answer that comes back is about their
     * business rather than about the category. Every one is worded to land on
     * the causal intent ("why…") and a metric the router already knows, which
     * is what gets a computed answer rather than the model fallback.
     *
     * The default is deliberately still a real question rather than the title
     * with a question mark: a detector added later should hand Ask something
     * answerable on the day it ships, not on the day somebody remembers to
     * come back here.
     */
    public function askQuestion(): string
    {
        $f = $this->facts ?? [];
        $n = fn (string $k) => number_format((float) ($f[$k] ?? 0));

        return match ($this->detector) {
            'staleLeads' => 'Why have '.$n('count').' leads gone stale, and what should I do about them?',
            'hotUnworked' => 'Why are my highest-scoring leads not being contacted, and what should I do?',
            'sourceQuality' => 'Why is '.($f['source'] ?? 'that source').' converting so much worse '
                .'than my other lead sources, and should I keep spending on it?',
            'campaignBurn' => 'Why is the '.($f['name'] ?? 'campaign').' campaign underperforming, '
                .'and should I pause it?',
            // Bedrooms is 0 on land and commercial units, so the count is only
            // written in when there is one — "0-bed Office Spaces" reads as a
            // bug to the person being shown it, and rightly.
            'demandGap' => 'Why is demand for '
                .(($f['bedrooms'] ?? 0) > 0 ? $f['bedrooms'].'-bed ' : '')
                .($f['type'] ?? 'units').'s in '.($f['location'] ?? 'that area')
                .' so far ahead of my supply, and what should I do?',
            'staleInventory' => 'Why are '.$n('count').' of my listings sitting unsold for so long, '
                .'and how do I move them?',
            'agentDivergence' => 'Why are some of my realtors closing at half the rate of the others, '
                .'and how do I close the gap?',
            'funnelErosion' => 'Why are fewer leads advancing after a site visit than they used to, '
                .'and what should I change?',
            'revenueTrend' => 'Why has revenue been '.(($f['direction'] ?? 'down') === 'down'
                ? 'falling' : 'growing').', and what is driving it?',
            'revenueConcentration' => 'How exposed am I to losing my top realtors, and what should '
                .'I do to spread the revenue?',
            'paymentRisk' => 'Why is so much money overdue, and how do I collect it?',
            'collectionHealth' => 'Why is my collection ratio below target, and how do I improve it?',
            'feedbackCluster' => 'Why are customers complaining about '.($f['tag'] ?? 'this')
                .', and how do I fix it?',
            'customerRisk' => 'Why are '.$n('count').' of my investors unhappy, and what should '
                .'I do about them?',
            default => 'Why is this happening — '.$this->title.' — and what should I do about it?',
        };
    }
}
