<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ServesTheClient;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client portal — documents.
 *
 * Availability is DERIVED from the purchase, not hardcoded. Title documents
 * genuinely do wait on full settlement in a Nigerian instalment purchase, so
 * the portal says so and names what each one is waiting on. Nothing here is
 * pending because somebody forgot about it.
 */
#[Layout('layouts.portal')]
#[Title('Documents')]
class Documents extends Component
{
    use ServesTheClient;

    /**
     * Everything available, in one file.
     *
     * A zip rather than the prototype's trick of firing N downloads 180ms
     * apart — browsers block that as a popup after the second file, so that
     * button only ever looked like it worked.
     */
    public function downloadAll(): ?StreamedResponse
    {
        $ready = collect($this->documents())->where('ready', true);

        if ($ready->isEmpty()) {
            $this->dispatch('toast', [
                'title' => 'Nothing to download yet',
                'msg' => 'Your documents appear here as they are issued.',
            ]);

            return null;
        }

        if (! class_exists(\ZipArchive::class)) {
            $this->dispatch('toast', [
                'title' => 'Download them individually',
                'msg' => 'Bulk download needs the zip extension, which this server does not have.',
                'kind' => 'neg',
            ]);

            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'nexabi');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE | \ZipArchive::CREATE);

        foreach ($ready as $doc) {
            $zip->addFromString(str($doc['name'])->slug().'.txt', $this->body($doc));
        }

        $zip->close();

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, str($this->customer()->name)->slug().'-documents.zip', [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function download(string $key): StreamedResponse
    {
        $doc = collect($this->documents())->keyBy('key')->get($key);

        abort_if(! $doc || ! $doc['ready'], 404);

        return response()->streamDownload(
            fn () => print($this->body($doc)),
            str($doc['name'])->slug().'.txt',
            ['Content-Type' => 'text/plain'],
        );
    }

    /** The text of one document. Shared by the single and the bulk download. */
    private function body(array $doc): string
    {
        $p = $this->purchase();

        return implode("\n", [
            mb_strtoupper(app('nexabi.tenant')->name),
            '',
            sprintf('%-16s %s', 'Document', $doc['name']),
            sprintf('%-16s %s', 'Issued', $doc['at'] ? Fmt::date($doc['at']) : '—'),
            sprintf('%-16s %s', 'Customer', $this->customer()->name),
            sprintf('%-16s %s', 'Property', ($p->property->title ?? '—').', '.($p->property->location ?? '')),
            sprintf('%-16s %s', 'Reference', $p->property->ref ?? '—'),
            sprintf('%-16s %s', 'Purchase price', Fmt::money($p->total())),
            '',
            $doc['note'],
            '',
            'Generated '.now()->format('j M Y, H:i'),
        ]);
    }

    public function receipt(int $paymentId): StreamedResponse
    {
        $p = $this->purchase();
        $payment = $p->payments->firstWhere('id', $paymentId);

        // Scoped to this buyer's own purchase, so an id from someone else's
        // account resolves to nothing rather than to their receipt.
        abort_if(! $payment || ! $payment->paid_at, 404);

        $lines = implode("
", [
            mb_strtoupper(app('nexabi.tenant')->name).' - PAYMENT RECEIPT',
            '',
            sprintf('%-14s %s', 'Receipt no.', 'NX-R'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)),
            sprintf('%-14s %s', 'Customer', $this->customer()->name),
            sprintf('%-14s %s', 'Instalment', $payment->instalment.' of '.$payment->of_total),
            sprintf('%-14s %s', 'Amount', Fmt::money($payment->amount)),
            sprintf('%-14s %s', 'Paid', Fmt::date($payment->paid_at)),
            sprintf('%-14s %s', 'Method', $payment->method),
            '',
            'Issued '.now()->format('j M Y, H:i'),
        ]);

        return response()->streamDownload(
            fn () => print($lines),
            'receipt-'.$payment->id.'.txt',
            ['Content-Type' => 'text/plain'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function documents(): array
    {
        $p = $this->purchase();
        $closed = $p->deal?->closed_at ?? $this->customer()->since;
        $survey = $closed?->copy()->addDays(21);

        return [
            [
                'key' => 'offer-letter',
                'name' => 'Offer letter',
                'note' => 'Terms, price and payment plan as agreed',
                'at' => $closed?->copy()->subDays(7),
                'ready' => true,
            ],
            [
                'key' => 'contract',
                'name' => 'Contract of sale',
                'note' => 'Signed purchase agreement',
                'at' => $closed,
                'ready' => true,
            ],
            [
                'key' => 'schedule',
                'name' => 'Payment schedule',
                'note' => $p->payments->count().' instalments · '.Fmt::moneyShort($p->total()),
                'at' => $closed,
                'ready' => true,
            ],
            [
                'key' => 'survey',
                'name' => 'Survey plan',
                'note' => 'Registered surveyor drawing and coordinates',
                'at' => $survey,
                'ready' => $survey && $survey->isPast(),
                'blockedBy' => 'With the surveyor',
            ],
            [
                'key' => 'deed',
                'name' => 'Deed of assignment',
                'note' => $p->settled()
                    ? 'Executed and ready for collection'
                    : 'Issued on full settlement of the purchase price',
                'at' => null,
                'ready' => $p->settled(),
                'blockedBy' => $p->settled() ? null : Fmt::moneyShort($p->outstanding()).' outstanding',
            ],
            [
                'key' => 'cofo',
                'name' => 'Certificate of Occupancy',
                'note' => $p->settled()
                    ? "Governor's consent processing — 60–90 days"
                    : 'Begins after the deed of assignment is executed',
                'at' => null,
                'ready' => false,
                'blockedBy' => $p->settled() ? 'In processing' : 'Awaiting full settlement',
            ],
            [
                'key' => 'handover',
                'name' => 'Handover checklist',
                'note' => 'Final inspection, meter readings and keys',
                'at' => null,
                'ready' => false,
                'blockedBy' => $p->settled() ? 'Schedule with your agent' : 'Available at settlement',
            ],
        ];
    }

    public function render()
    {
        $docs = collect($this->documents());

        return view('livewire.portal.documents', [
            'p' => $this->purchase(),
            'ready' => $docs->where('ready', true)->values(),
            'pending' => $docs->where('ready', false)->values(),
        ]);
    }
}
