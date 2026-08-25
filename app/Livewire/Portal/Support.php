<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Mail\SupportTicketRaised;
use App\Mail\SupportTicketReceived;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Client portal — support.
 *
 * Tickets are rows, not browser storage. That is the difference between a
 * prototype and a product: what the buyer writes here is in the same database
 * the agency reads, attached to their agent, and countable by the detectors.
 */
#[Layout('layouts.portal')]
#[Title('Support')]
class Support extends Component
{
    use ServesTheClient;

    public const TOPICS = [
        'payment-plan' => 'Change my payment plan',
        'payment-query' => 'Question about a payment',
        'documents' => 'Documents and title',
        'property' => 'Something about the property',
        'handover' => 'Handover and keys',
        'other' => 'Something else',
    ];

    #[Url(except: '')]
    public string $topic = 'payment-plan';

    /**
     * Fifteen characters is not arbitrary: "it's broken" is a ticket that
     * costs two more round trips before anyone can act on it.
     */
    #[Validate('required|string|min:15|max:2000')]
    public string $body = '';

    public function mount(): void
    {
        if (! isset(self::TOPICS[$this->topic])) {
            $this->topic = 'payment-plan';
        }
    }

    public function send(): void
    {
        $this->validate();

        $customer = $this->customer();

        $ticket = SupportTicket::create([
            'tenant_id' => app('nexabi.tenant')->id,
            'customer_id' => $customer->id,
            'agent_id' => $customer->agent_id,
            'reference' => 'TCK-'.mb_strtoupper(base_convert((string) now()->timestamp, 10, 36))
                .'-'.mb_strtoupper(str()->random(3)),
            'topic' => self::TOPICS[$this->topic] ?? 'Other',
            'body' => $this->body,
            'status' => 'open',
            // Acknowledged immediately and honestly: this is an automatic
            // receipt, and it says so rather than impersonating the agent.
            'reply' => 'Received. Your agent has been notified and will respond within one working day.',
            'replied_at' => now(),
        ]);

        $this->notify($ticket);

        $this->reset('body');

        $this->dispatch('toast', [
            'title' => 'Sent to '.($customer->agent?->name ?? 'your agent'),
            'msg' => 'Reference '.$ticket->reference.' — usually answered within a day.',
            'kind' => 'pos',
        ]);
    }

    /**
     * Tell the two people who need to know.
     *
     * Wrapped, because a mail failure must not lose the ticket. The row is
     * already committed by this point; the worst case is that someone has to
     * find it in the console instead of their inbox.
     */
    private function notify(SupportTicket $ticket): void
    {
        try {
            if ($ticket->agent?->email) {
                Mail::to($ticket->agent->email)->send(new SupportTicketRaised($ticket));
            }

            if ($ticket->customer->email) {
                Mail::to($ticket->customer->email)->send(new SupportTicketReceived($ticket));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function render()
    {
        return view('livewire.portal.support', [
            'customer' => $this->customer(),
            'p' => $this->purchase(),
            'tickets' => SupportTicket::where('customer_id', $this->customer()->id)
                ->latest()->limit(12)->get(),
            'feedback' => $this->customer()->feedback()->latest()->get(),
        ]);
    }
}
