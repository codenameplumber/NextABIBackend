<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Client portal — the overview.
 *
 * One question brought them here: is anything owed, and when. The banner
 * answers it in a sentence before anything else on the page loads, and the
 * wording changes with the actual state of the account rather than being one
 * template with a number swapped in.
 */
#[Layout('layouts.portal')]
#[Title('My property')]
class Overview extends Component
{
    use ServesTheClient;

    public function render()
    {
        $p = $this->purchase();
        $next = $p->next();

        return view('livewire.portal.overview', [
            'customer' => $this->customer(),
            'p' => $p,
            'next' => $next,
            'banner' => $this->banner($p, $next),
            'journey' => $this->journey($p),
        ]);
    }

    /**
     * The status banner.
     *
     * Four genuinely different situations, four genuinely different messages.
     * A late buyer is told what it costs them and offered a way out; a settled
     * one is told they are done and pointed at their documents.
     */
    private function banner($p, $next): array
    {
        if ($p->settled()) {
            return [
                'headline' => 'Your purchase is fully settled.',
                'body' => 'All <b>'.$p->payments->count().'</b> instalments totalling <b>'
                    .Fmt::moneyShort($p->total()).'</b> have been received. Your documents are in '
                    .'the Documents tab.',
            ];
        }

        if ($p->lateCount()) {
            $worst = $p->worstLateBy();

            return [
                'headline' => 'You have '.$p->lateCount().' payment'.($p->lateCount() > 1 ? 's' : '')
                    .' past due.',
                'body' => '<b>'.Fmt::money($p->lateAmount()).'</b> is outstanding, the oldest by <b>'
                    .$worst.' '.($worst === 1 ? 'day' : 'days').'</b>. Settling it keeps your handover '
                    .'on track — and if the timing is hard, your agent can rearrange the remaining '
                    .'schedule.',
            ];
        }

        if ($next) {
            $days = (int) now()->diffInDays($next->due_at, absolute: true);

            return [
                'headline' => $next->due_at->isFuture()
                    ? 'Your next payment is due in '.$days.' '.($days === 1 ? 'day' : 'days').'.'
                    : 'Your next payment is due now.',
                'body' => 'Instalment <b>'.$next->instalment.' of '.$next->of_total.'</b> — <b>'
                    .Fmt::money($next->amount).'</b> on <b>'.Fmt::date($next->due_at).'</b>. You have '
                    .'paid <b>'.Fmt::pct($p->progress(), 0).'</b> of the purchase price so far.',
            ];
        }

        return [
            'headline' => 'Your purchase is on track.',
            'body' => 'Nothing needs your attention right now.',
        ];
    }

    /** Where they are in the process, in the words a buyer would use. */
    private function journey($p): array
    {
        $closed = $p->deal?->closed_at ?? $this->customer()->since;
        $handover = $p->payments->max('due_at')?->copy()->addDays(14) ?? $closed;
        $lead = $p->deal?->lead;

        return [
            [
                'title' => 'Enquiry received',
                'detail' => 'You first contacted us about a '
                    .mb_strtolower($p->property->type ?? 'property').' in '
                    .($p->property->location ?? 'Lagos').'.',
                'at' => $lead?->created_at,
                'state' => 'done',
            ],
            [
                'title' => 'Site visit',
                'detail' => 'You inspected the unit with your agent.',
                'at' => $lead?->created_at?->copy()->addDays(12),
                'state' => 'done',
            ],
            [
                'title' => 'Offer accepted',
                'detail' => 'Terms agreed and the instalment plan was set.',
                'at' => $closed?->copy()->subDays(7),
                'state' => 'done',
            ],
            [
                'title' => 'Contract signed',
                'detail' => 'Purchase completed at '.Fmt::moneyShort($p->deal->value ?? 0).'.',
                'at' => $closed,
                'state' => 'done',
            ],
            [
                'title' => 'Instalments',
                'detail' => $p->settled()
                    ? 'All '.$p->payments->count().' instalments received.'
                    : $p->paid()->count().' of '.$p->payments->count().' received. '
                        .Fmt::moneyShort($p->outstanding()).' remaining.',
                'at' => null,
                'state' => $p->settled() ? 'done' : 'now',
            ],
            [
                'title' => 'Handover',
                'detail' => 'Keys, title documents and final inspection.',
                'at' => $handover,
                'state' => 'todo',
            ],
        ];
    }
}
