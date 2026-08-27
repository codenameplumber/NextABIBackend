<?php

namespace App\Platform;

use App\Models\Deal;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Recording an instalment, and moving the journey along with it.
 *
 * Payments arrive as bank transfers. An admin keys them in, which means every
 * recorded payment needs a reference and a recorder — a disputed instalment
 * with neither is a conversation nobody can win.
 *
 * Recording the last instalment completes the commitment. That happens here
 * rather than on a schedule, because a buyer who pays off their flat should see
 * it settled on the same screen refresh, not tomorrow morning.
 */
class Instalments
{
    /**
     * Mark one instalment paid.
     *
     * Deliberately refuses to re-record. Keying the same transfer twice is the
     * single easiest mistake to make on a busy morning, and the second entry
     * would silently overstate collections.
     */
    public function record(Payment $instalment, User $admin, ?string $reference = null, ?float $amount = null): array
    {
        if ($instalment->isSettled()) {
            return ['ok' => false, 'message' => 'That instalment is already recorded as paid.'];
        }

        $commitment = $instalment->commitment;

        $result = DB::transaction(function () use ($instalment, $admin, $reference, $amount, $commitment) {
            $instalment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'amount' => $amount ?? $instalment->amount,
                'method' => 'transfer',
                'recorded_by' => $admin->id,
                'recorded_at' => now(),
                'reference' => $reference,
            ])->save();

            if (! $commitment) {
                return ['completed' => false, 'first' => false];
            }

            $commitment->load('instalments');

            $paid = $commitment->instalments->filter(fn (Payment $p) => $p->isSettled())->count();
            $completed = $paid >= $commitment->instalments->count();

            if ($completed) {
                $commitment->forceFill([
                    'status' => 'completed',
                    'won' => true,
                    'closed_at' => now(),
                    'cycle_days' => (int) $commitment->committed_at?->diffInDays(now()),
                ])->save();
            } elseif ($commitment->status === 'approved') {
                $commitment->forceFill(['status' => 'active'])->save();
            }

            return ['completed' => $completed, 'first' => $paid === 1];
        });

        return [
            'ok' => true,
            'completed' => $result['completed'],
            // The two moments worth asking a buyer how it is going. Feedback
            // has no menu item any more, but two detectors still run on it.
            'feedbackDue' => $result['completed'] ? 'completed'
                : ($result['first'] ? 'journey_started' : null),
            'message' => $result['completed']
                ? 'Final instalment recorded — the commitment is settled.'
                : 'Instalment recorded.',
        ];
    }

    /**
     * Undo a recording.
     *
     * Mis-keyed payments happen, and without a way back the only remedy is
     * editing the database by hand. Reopening a completed commitment is part of
     * it — a reversal that leaves the parent marked settled is a lie.
     */
    public function reverse(Payment $instalment, User $admin): array
    {
        if (! $instalment->isSettled()) {
            return ['ok' => false, 'message' => 'That instalment is not recorded as paid.'];
        }

        DB::transaction(function () use ($instalment) {
            $instalment->forceFill([
                'status' => 'scheduled',
                'paid_at' => null,
                'recorded_by' => null,
                'recorded_at' => null,
                'reference' => null,
            ])->save();

            $commitment = $instalment->commitment;

            if ($commitment?->isCompleted()) {
                $commitment->forceFill([
                    'status' => 'active',
                    'won' => false,
                    'closed_at' => null,
                ])->save();
            }
        });

        return ['ok' => true, 'message' => 'Recording reversed.'];
    }

    /**
     * What an admin should be looking at today.
     *
     * Ordered by how late it is, because the oldest arrear is the one most
     * likely to stop being collectable.
     */
    public function dueNow(int $limit = 50): \Illuminate\Support\Collection
    {
        return Payment::query()
            ->whereNull('paid_at')
            ->where('due_at', '<=', now())
            ->with(['investor', 'commitment.property'])
            ->orderBy('due_at')
            ->limit($limit)
            ->get();
    }
}
