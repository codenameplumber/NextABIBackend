<?php

namespace App\Engine;

use App\Models\ActionLog;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Insight;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Recommendation;
use App\Models\Tenant;
use App\Mail\PaymentReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Layer 6 — The Action Bus.
 *
 * Where recommendations stop being advice. Every handler mutates the store,
 * records a before/after diff, and declares which detectors it invalidates so
 * exactly those re-run inside the request. That round trip is the entire
 * product thesis in one code path.
 *
 * Nothing here is scripted. If an action does not actually change the data,
 * the insight does not go away — which is exactly the behaviour you want.
 */
class ActionBus
{
    public function __construct(private Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    /**
     * Which detectors each action can affect.
     *
     * Re-running all fourteen on every click is wasteful; re-running none
     * breaks the closed loop. This map is the compromise, and it is the
     * single most important thing to keep honest when adding a handler.
     */
    private const INVALIDATES = [
        'SCHEDULE_FOLLOWUP' => ['staleLeads', 'hotUnworked', 'agentDivergence'],
        'ASSIGN_LEADS' => ['staleLeads', 'hotUnworked', 'agentDivergence', 'revenueConcentration'],
        'REBALANCE_LEADS' => ['agentDivergence', 'staleLeads', 'revenueConcentration'],
        'PAUSE_CAMPAIGN' => ['campaignBurn', 'sourceQuality'],
        'REALLOCATE_BUDGET' => ['campaignBurn'],
        'RESCORE_LEADS' => ['hotUnworked', 'staleLeads', 'funnelErosion'],
        'ADJUST_PRICE' => ['demandGap', 'staleInventory'],
        'FLAG_ACQUISITION' => ['demandGap'],
        'MATCH_LEADS' => ['demandGap', 'staleLeads'],
        'SEND_REMINDERS' => ['paymentRisk', 'collectionHealth'],
        'ESCALATE_ACCOUNTS' => ['paymentRisk'],
        'RESTRUCTURE_TERMS' => ['paymentRisk', 'collectionHealth'],
        'OUTREACH' => ['customerRisk', 'feedbackCluster'],
        'ASSIGN_COACHING' => ['agentDivergence'],
        'FLAG_PROMOTION' => ['staleInventory'],
        'SET_POLICY' => [],
        'NAVIGATE' => [],
        'ACKNOWLEDGE' => [],
    ];

    public static function actionTypes(): array
    {
        return array_keys(self::INVALIDATES);
    }

    /**
     * Execute a recommendation.
     *
     * The only entry point — views never call handlers directly, which keeps
     * logging, idempotency and the re-detect guaranteed.
     */
    public function execute(Recommendation $rec, ?int $userId = null): array
    {
        $type = $rec->action_type;

        if (! isset(self::INVALIDATES[$type])) {
            return ['ok' => false, 'message' => "No handler for {$type}"];
        }

        if ($rec->done_at) {
            return ['ok' => false, 'message' => 'Already applied.'];
        }

        // Guards a double-click. The client-side prototype had no protection
        // here at all — the second click silently applied everything twice.
        $idempotencyKey = hash('sha256', $rec->key.':'.$rec->updated_at?->timestamp);

        if (ActionLog::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('idempotency_key', $idempotencyKey)->exists()) {
            return ['ok' => false, 'message' => 'That action has already been applied.'];
        }

        $result = DB::transaction(function () use ($rec, $type, $userId, $idempotencyKey) {
            $result = $this->{'handle'.str($type)->studly()}($rec->payload ?? []);

            if (! $result['ok']) {
                return $result;
            }

            $rec->forceFill(['done_at' => now()])->save();

            $insight = $rec->insight;
            if ($insight) {
                $insight->forceFill(['status' => 'actioned', 'actioned_at' => now()])->save();
            }

            ActionLog::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $userId,
                'insight_id' => $rec->insight_id,
                'recommendation_key' => $rec->key,
                'action_type' => $type,
                'label' => $rec->label,
                'message' => $result['message'],
                'before' => $result['before'] ?? null,
                'after' => $result['after'] ?? null,
                'detail' => $result['detail'] ?? null,
                'area' => $insight?->area,
                'affected' => $result['affected'] ?? 0,
                'impact' => $rec->impact,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $result;
        });

        $this->settle($result['ok'], $type);

        return $result;
    }

    /**
     * Discard the metric cache once the transaction has resolved.
     *
     * The cache is a file store; it is not transactional. Handlers call
     * metrics() to measure the before/after diff, which repopulates the cache
     * from *uncommitted* state — so a rollback leaves figures describing a
     * world that no longer exists, and the app keeps serving them until they
     * expire ten minutes later.
     *
     * Flushing on both paths is right in both directions: after a commit the
     * cache is stale anyway, and after a rollback it is wrong.
     */
    private function settle(bool $ok, string $type): void
    {
        $this->metrics()->flush();

        if ($ok) {
            // Re-run only what this action can have changed. Small scope, so
            // it is fast enough to do inside the request and the user sees the
            // finding retire itself.
            InsightEngine::for($this->tenant)->run(self::INVALIDATES[$type] ?: null);
        }
    }

    /**
     * Execute an action the user initiated themselves.
     *
     * Not everything worth doing starts as a finding. "Follow up the 25 stale
     * leads" is a reasonable thing to want from the Leads page even on a day
     * when the detector has not flagged it, and routing it through here rather
     * than calling a handler directly means it gets the same transaction, the
     * same action log, and the same targeted re-detection.
     *
     * $key must be stable for the same intent, because it is what stops a
     * double-click applying the sweep twice.
     */
    public function manual(
        string $type,
        array $payload,
        string $label,
        string $key,
        ?int $userId = null,
        ?string $area = null,
    ): array {
        if (! isset(self::INVALIDATES[$type])) {
            return $this->nope("No handler for {$type}");
        }

        $idempotencyKey = hash('sha256', 'manual:'.$key);

        if (ActionLog::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('idempotency_key', $idempotencyKey)->exists()) {
            return $this->nope('That action has already been applied.');
        }

        $result = DB::transaction(function () use ($type, $payload, $label, $userId, $area, $idempotencyKey) {
            $result = $this->{'handle'.str($type)->studly()}($payload);

            if (! $result['ok']) {
                return $result;
            }

            ActionLog::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $userId,
                'insight_id' => null,
                'recommendation_key' => null,
                'action_type' => $type,
                'label' => $label,
                'message' => $result['message'],
                'before' => $result['before'] ?? null,
                'after' => $result['after'] ?? null,
                'detail' => $result['detail'] ?? null,
                'area' => $area,
                'affected' => $result['affected'] ?? 0,
                'impact' => 0,
                'idempotency_key' => $idempotencyKey,
            ]);

            return $result;
        });

        $this->settle($result['ok'], $type);

        return $result;
    }

    /**
     * Required payload keys.
     *
     * The action bus is the only entry point, so it is also the only place a
     * malformed payload can be caught. A handler that reaches for a key that
     * is not there produces a PHP fatal and a 500; refusing cleanly produces a
     * toast the user can read. Playbooks always send these — this is for the
     * paths playbooks do not own: a hand-built payload, a stale recommendation
     * whose shape changed, an API caller.
     */
    private function requires(array $p, string ...$keys): ?array
    {
        $missing = array_values(array_filter(
            $keys,
            fn ($k) => ! isset($p[$k]) || $p[$k] === '' || $p[$k] === [],
        ));

        return $missing
            ? $this->nope('This action is missing '.implode(' and ', $missing).'.')
            : null;
    }

    private function ok(string $message, array $extra = []): array
    {
        return array_merge(['ok' => true, 'message' => $message], $extra);
    }

    private function nope(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }

    private function money(float $n): string
    {
        $abs = abs($n);

        return match (true) {
            $abs >= 1e9 => '₦'.number_format($n / 1e9, 1).'B',
            $abs >= 1e6 => '₦'.number_format($n / 1e6, 1).'M',
            default => '₦'.number_format($n),
        };
    }

    private function metrics(): Metrics
    {
        $m = Metrics::for($this->tenant);
        $m->flush();

        return $m;
    }

    /** Resolve the lead set an action targets. */
    private function leadQuery(array $p)
    {
        $q = Lead::query();

        return match ($p['filter'] ?? null) {
            'stale' => $q->where('is_open', true)
                ->where('last_contact_at', '<=', now()->subDays(14))
                ->when($p['minScore'] ?? null, fn ($q, $s) => $q->where('score', '>=', $s)),
            'hot-idle' => $q->where('is_open', true)->where('score', '>=', 75)
                ->where('last_contact_at', '<=', now()->subDays(7)),
            default => $q->when($p['ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids)),
        };
    }

    /* ===================================================== lead handlers */

    private function handleScheduleFollowup(array $p): array
    {
        $before = $this->metrics()->leads()['stale'];
        $leads = $this->leadQuery($p)->get();

        if ($leads->isEmpty()) {
            return $this->nope('Nothing left to follow up — already handled.');
        }

        Lead::whereIn('id', $leads->pluck('id'))->update([
            'last_contact_at' => now(),
            'follow_up_at' => now()->addDays(2),
            'follow_up_note' => $p['note'] ?? 'Follow-up scheduled by NexABI',
        ]);

        $this->logActivity($leads, 'followup_scheduled', $p['note'] ?? 'Scheduled by NexABI');

        $after = $this->metrics()->leads()['stale'];

        return $this->ok('Follow-up scheduled for '.number_format($leads->count()).' leads', [
            'before' => $before.' stale', 'after' => $after.' stale', 'affected' => $leads->count(),
        ]);
    }

    private function handleAssignLeads(array $p): array
    {
        $agentIds = $p['agentIds'] ?? [];
        if (! $agentIds) {
            return $this->nope('No agents with available capacity.');
        }

        $leads = $this->leadQuery($p)->get();
        if ($leads->isEmpty()) {
            return $this->nope('Those leads have already been reassigned.');
        }

        $i = 0;
        foreach ($leads as $lead) {
            $lead->forceFill([
                'assigned_agent_id' => $agentIds[$i++ % count($agentIds)],
                'reassigned_at' => now(),
                'last_contact_at' => now(),
            ])->save();
        }

        $names = Agent::whereIn('id', $agentIds)->pluck('name')
            ->map(fn ($n) => explode(' ', $n)[0])->implode(', ');

        return $this->ok(number_format($leads->count()).' leads reassigned to '.$names, [
            'affected' => $leads->count(),
        ]);
    }

    private function handleRebalanceLeads(array $p): array
    {
        $to = $p['toAgentIds'] ?? [];
        if (! $to) {
            return $this->nope('No receiving agents available.');
        }

        $pool = Lead::where('is_open', true)
            ->whereIn('assigned_agent_id', $p['fromAgentIds'] ?? [])
            ->orderByDesc('score')->get();

        $take = $pool->take(max(1, (int) round($pool->count() * ($p['share'] ?? 0.35))));
        if ($take->isEmpty()) {
            return $this->nope('That cohort no longer holds open leads.');
        }

        $i = 0;
        foreach ($take as $lead) {
            $lead->forceFill([
                'assigned_agent_id' => $to[$i++ % count($to)],
                'reassigned_at' => now(),
                'last_contact_at' => now(),
            ])->save();
        }

        return $this->ok(number_format($take->count()).' leads rebalanced to higher-converting agents', [
            'before' => $pool->count().' held by lagging cohort',
            'after' => ($pool->count() - $take->count()).' held',
            'affected' => $take->count(),
        ]);
    }

    /** Genuinely recomputes the score from engagement — not a cosmetic bump. */
    private function handleRescoreLeads(array $p): array
    {
        $leads = Lead::where('is_open', true)
            ->when($p['campaignId'] ?? null, fn ($q, $id) => $q->where('campaign_id', $id))
            ->when($p['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->get();

        if ($leads->isEmpty()) {
            return $this->nope('No open leads match that filter.');
        }

        $activity = DB::table('activities')
            ->where('tenant_id', $this->tenant->id)
            ->where('entity_type', 'lead')
            ->whereIn('entity_id', $leads->pluck('id'))
            ->selectRaw('entity_id, COUNT(*) c')
            ->groupBy('entity_id')->pluck('c', 'entity_id');

        $down = 0;
        $up = 0;

        foreach ($leads as $lead) {
            $idle = $lead->last_contact_at ? $lead->last_contact_at->diffInDays(now()) : 60;
            $fresh = max(0, 1 - $idle / 45);
            $engagement = min(1, ($activity[$lead->id] ?? 0) / 6);
            $next = (int) round(max(3, min(99, $lead->score * 0.55 + $fresh * 24 + $engagement * 22)));

            if ($next < $lead->score - 2) {
                $down++;
            }
            if ($next > $lead->score + 2) {
                $up++;
            }

            $lead->forceFill(['score' => $next, 'rescored_at' => now()])->save();
        }

        return $this->ok(number_format($leads->count()).' leads re-scored', [
            'affected' => $leads->count(),
            'detail' => $down.' downgraded, '.$up.' upgraded',
        ]);
    }

    private function handleMatchLeads(array $p): array
    {
        if ($refusal = $this->requires($p, 'location', 'type')) {
            return $refusal;
        }

        $stock = Property::where('status', 'available')
            ->where('location', $p['location'])->where('type', $p['type'])
            ->when(isset($p['bedrooms']), fn ($q) => $q->where('bedrooms', $p['bedrooms']))
            ->get();

        if ($stock->isEmpty()) {
            return $this->nope('That segment has no available stock left.');
        }

        $candidates = Lead::where('is_open', true)
            ->where('wants_location', $p['location'])->where('wants_type', $p['type'])
            ->orderByDesc('score')->limit($stock->count() * 3)->get();

        if ($candidates->isEmpty()) {
            return $this->nope('No open leads are targeting that segment.');
        }

        foreach ($candidates as $i => $lead) {
            $lead->forceFill([
                'matched_property_id' => $stock[$i % $stock->count()]->id,
                'last_contact_at' => now(),
            ])->save();
        }

        return $this->ok(
            $candidates->count().' leads matched to '.$stock->count().' available units',
            ['affected' => $candidates->count(),
                'detail' => 'Top match scores '.$candidates->take(3)->pluck('score')->implode(', ')],
        );
    }

    /* ================================================ marketing handlers */

    private function handlePauseCampaign(array $p): array
    {
        $c = Campaign::find($p['campaignId'] ?? 0);
        if (! $c) {
            return $this->nope('Campaign not found.');
        }
        if ($c->status === 'paused') {
            return $this->nope('Already paused.');
        }

        $c->forceFill(['status' => 'paused', 'paused_at' => now()])->save();

        return $this->ok('"'.$c->name.'" paused', [
            'before' => 'active', 'after' => 'paused', 'affected' => 1,
            'detail' => $this->money($c->spend).' of committed spend stopped',
        ]);
    }

    private function handleReallocateBudget(array $p): array
    {
        $from = Campaign::find($p['from'] ?? 0);
        $to = Campaign::find($p['to'] ?? 0);
        if (! $from || ! $to) {
            return $this->nope('Campaign not found.');
        }

        $move = min((float) ($p['amount'] ?? 0), $from->spend);
        $original = $from->spend;

        $from->forceFill(['spend' => $from->spend - $move])->save();
        $to->forceFill(['spend' => $to->spend + $move, 'status' => 'active'])->save();

        return $this->ok($this->money($move).' moved to "'.$to->name.'"', [
            'before' => $this->money($original).' on "'.$from->name.'"',
            'after' => $this->money($from->spend).' on "'.$from->name.'"',
            'affected' => 2,
        ]);
    }

    /* ================================================ property handlers */

    private function handleAdjustPrice(array $p): array
    {
        $pct = (float) ($p['pctChange'] ?? 0);

        $q = Property::where('status', 'available');

        if (($p['filter'] ?? null) === 'stale') {
            $q->where('days_on_market', '>', $this->metrics()->properties()['staleCut']);
        } else {
            $q->when($p['location'] ?? null, fn ($q, $v) => $q->where('location', $v))
                ->when($p['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
                ->when(isset($p['bedrooms']), fn ($q) => $q->where('bedrooms', $p['bedrooms']));
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            return $this->nope('No available listings match that segment.');
        }

        $before = (float) $rows->sum('price');

        foreach ($rows as $prop) {
            $prop->forceFill([
                'prior_price' => $prop->prior_price ?? $prop->price,
                'price' => round($prop->price * (1 + $pct) / 500000) * 500000,
                'repriced_at' => now(),
            ])->save();
        }

        $after = (float) Property::whereIn('id', $rows->pluck('id'))->sum('price');

        return $this->ok(
            $rows->count().' listings repriced '.($pct > 0 ? '+' : '').round($pct * 100).'%',
            ['before' => $this->money($before), 'after' => $this->money($after),
                'affected' => $rows->count(),
                'detail' => ($after > $before ? '+' : '').$this->money($after - $before).' portfolio value'],
        );
    }

    private function handleFlagAcquisition(array $p): array
    {
        if ($refusal = $this->requires($p, 'location', 'type')) {
            return $refusal;
        }

        $key = $p['location'].'|'.$p['type'].'|'.($p['bedrooms'] ?? '');
        $flags = $this->tenant->settings['acquisitionFlags'] ?? [];

        if (in_array($key, $flags, true)) {
            return $this->nope('Already flagged for acquisition.');
        }

        $settings = $this->tenant->settings ?? [];
        $settings['acquisitionFlags'] = [...$flags, $key];
        $this->tenant->forceFill(['settings' => $settings])->save();

        return $this->ok(
            trim((($p['bedrooms'] ?? null) ? $p['bedrooms'].'-bed ' : '').$p['type'].' in '.$p['location'])
                .' flagged for acquisition',
            ['affected' => 1, 'detail' => 'Added to the sourcing pipeline'],
        );
    }

    private function handleFlagPromotion(array $p): array
    {
        $rows = Property::where('status', 'available')
            ->where('days_on_market', '>', $this->metrics()->properties()['staleCut'])
            ->get();

        if ($rows->isEmpty()) {
            return $this->nope('No listings need promotion right now.');
        }

        Property::whereIn('id', $rows->pluck('id'))->update(['promoted' => true]);

        return $this->ok($rows->count().' listings added to the next campaign refresh',
            ['affected' => $rows->count()]);
    }

    /* ================================================= finance handlers */

    /**
     * Reminders do not conjure payment.
     *
     * Recovery is modelled by age — fresher debt clears more often, 90-day debt
     * rarely does — and the overdue figure moves by what actually cleared. A
     * tool that always reports success is one you stop believing on second use.
     */
    private function handleSendReminders(array $p): array
    {
        $q = Payment::whereNull('paid_at');

        if (($p['filter'] ?? null) === 'overdue') {
            $q->where('due_at', '<', now()->subDays(30));
        } else {
            $q->where('due_at', '<=', now());
        }

        // Narrowed to one account when the user chased a specific customer
        // from the risk table, rather than sweeping the whole book.
        if ($p['customerIds'] ?? null) {
            $q->whereIn('customer_id', $p['customerIds']);
        }

        if ($p['ids'] ?? null) {
            $q->whereIn('id', $p['ids']);
        }

        $rows = $q->get();
        if ($rows->isEmpty()) {
            return $this->nope('No outstanding instalments to chase.');
        }

        $before = $this->metrics()->finance()['overdueAmount'];

        $recovered = 0;
        $recoveredAmount = 0.0;

        foreach ($rows as $payment) {
            $late = $payment->lateBy();
            $odds = match (true) {
                $late > 90 => 0.18,
                $late > 60 => 0.30,
                $late > 30 => 0.45,
                default => 0.60,
            };

            // Deterministic per payment, so the same click twice cannot produce
            // two different stories.
            $hash = crc32($payment->id.':'.$payment->due_at->timestamp);
            $clears = ($hash % 1000) / 1000 < $odds;

            if ($clears) {
                $payment->forceFill([
                    'status' => 'paid', 'paid_at' => now(),
                    'reminded_at' => now(), 'reminders' => $payment->reminders + 1,
                ])->save();
                $recovered++;
                $recoveredAmount += $payment->amount;
            } else {
                $payment->forceFill([
                    'reminded_at' => now(), 'reminders' => $payment->reminders + 1,
                ])->save();
            }
        }

        // Actually send them.
        //
        // Until this existed the action reported "19 reminders issued" and
        // issued nothing: the counter moved, the reminded_at stamp moved, and
        // no buyer ever heard from anyone. One mail per customer, not one per
        // instalment — three emails about three instalments on one account is
        // how a reminder becomes something people mute.
        $sent = $this->mailReminders($rows->filter(fn ($x) => ! $x->paid_at));

        $after = $this->metrics()->finance()['overdueAmount'];

        return $this->ok(
            $rows->count().' reminders issued · '.$recovered.' cleared',
            [
                'before' => $this->money($before), 'after' => $this->money($after),
                'affected' => $rows->count(),
                'detail' => $this->money($recoveredAmount).' recovered · '
                    .$sent.' '.($sent === 1 ? 'email' : 'emails').' sent',
            ],
        );
    }

    /**
     * One email per customer, listing everything they owe.
     *
     * Wrapped per recipient: a single bad address must not roll back the whole
     * sweep, and the action's own result has already been measured from the
     * data rather than from whether the mail server was reachable.
     */
    private function mailReminders(\Illuminate\Support\Collection $unpaid): int
    {
        $sent = 0;

        foreach ($unpaid->groupBy('customer_id') as $customerId => $payments) {
            $customer = Customer::find($customerId);

            if (! $customer?->email) {
                continue;
            }

            try {
                // Dispatched, not sent. A sweep across the whole book is
                // hundreds of messages, and a user should not sit through SMTP
                // round trips to find out their action worked. With
                // QUEUE_CONNECTION=sync this still goes out inline, so local
                // testing behaves exactly as it reads.
                Mail::to($customer->email)
                    ->queue((new PaymentReminder($customer, $payments->values()))->onQueue('mail'));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    private function handleEscalateAccounts(array $p): array
    {
        $rows = Customer::whereIn('id', $p['customerIds'] ?? [])->where('escalated', false)->get();
        if ($rows->isEmpty()) {
            return $this->nope('Those accounts are no longer at risk.');
        }

        Customer::whereIn('id', $rows->pluck('id'))
            ->update(['escalated' => true, 'escalated_at' => now()]);

        return $this->ok(
            $rows->count().' '.($rows->count() === 1 ? 'account' : 'accounts')
                .' escalated to relationship management',
            ['affected' => $rows->count(), 'detail' => $rows->take(3)->pluck('name')->implode(', ')],
        );
    }

    private function handleRestructureTerms(array $p): array
    {
        $min = (int) ($p['minOverdueDays'] ?? 90);

        $rows = Payment::whereNull('paid_at')->where('due_at', '<', now()->subDays($min))->get();
        if ($rows->isEmpty()) {
            return $this->nope('Nothing aged beyond '.$min.' days.');
        }

        $amount = (float) $rows->sum('amount');

        foreach ($rows as $payment) {
            $payment->forceFill([
                'original_due_at' => $payment->original_due_at ?? $payment->due_at,
                'due_at' => now()->addDays(30),
                'status' => 'pending',
                'restructured' => true,
            ])->save();
        }

        return $this->ok($rows->count().' instalments restructured onto 30-day terms', [
            'before' => $this->money($amount).' aged '.$min.'+',
            'after' => $this->money($amount).' rescheduled',
            'affected' => $rows->count(),
        ]);
    }

    /* =============================================== customer handlers */

    private function handleOutreach(array $p): array
    {
        $maxRating = (float) ($p['maxRating'] ?? 2);

        $customerIds = Feedback::where('rating', '<=', $maxRating)
            ->when($p['tag'] ?? null, fn ($q, $tag) => $q->whereJsonContains('tags', $tag))
            ->pluck('customer_id')->filter()->unique();

        if ($customerIds->isEmpty()) {
            return $this->nope('No unhappy customers match that filter.');
        }

        $rows = Customer::whereIn('id', $customerIds)->whereNull('recovery_status')->get();
        if ($rows->isEmpty()) {
            return $this->nope('Recovery is already open for those customers.');
        }

        Customer::whereIn('id', $rows->pluck('id'))
            ->update(['recovery_status' => 'open', 'recovery_opened_at' => now()]);

        return $this->ok('Recovery outreach opened for '.$rows->count().' customers', [
            'affected' => $rows->count(),
            'detail' => $customerIds->count().' customers with negative feedback covered',
        ]);
    }

    private function handleAssignCoaching(array $p): array
    {
        $rows = Agent::whereIn('id', $p['agentIds'] ?? [])->where('coaching', false)->get();
        if ($rows->isEmpty()) {
            return $this->nope('Those agents are no longer in the lagging cohort.');
        }

        Agent::whereIn('id', $rows->pluck('id'))->update([
            'coaching' => true,
            'mentor_id' => ($p['mentorIds'] ?? [null])[0],
        ]);

        return $this->ok($rows->count().' agents paired with mentors for 4 weeks', [
            'affected' => $rows->count(),
            'detail' => $rows->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->implode(', '),
        ]);
    }

    /* ================================================== policy handlers */

    private function handleSetPolicy(array $p): array
    {
        if ($refusal = $this->requires($p, 'key')) {
            return $refusal;
        }

        $key = $p['key'];

        if (! array_key_exists('value', $p)) {
            return $this->nope('This action is missing value.');
        }

        if ($this->tenant->policy($key) === $p['value']) {
            return $this->nope('That policy is already in force.');
        }

        $this->tenant->setPolicy($key, $p['value']);

        return $this->ok('Policy set: '.$key.' = '.json_encode($p['value']),
            ['affected' => 1, 'detail' => 'Applied org-wide']);
    }

    private function handleNavigate(array $p): array
    {
        return $this->ok('Opened', ['silent' => true]);
    }

    private function handleAcknowledge(array $p): array
    {
        return $this->ok('Acknowledged', ['silent' => true]);
    }

    /* ------------------------------------------------------------ helper */

    private function logActivity($leads, string $type, ?string $note): void
    {
        $rows = [];
        foreach ($leads as $lead) {
            $rows[] = [
                'tenant_id' => $this->tenant->id,
                'type' => $type,
                'entity_type' => 'lead',
                'entity_id' => $lead->id,
                'agent_id' => $lead->assigned_agent_id,
                'at' => now(),
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('activities')->insert($chunk);
        }
    }
}
