<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Support\Fmt;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Marketing — spend efficiency by campaign and channel.
 *
 * ROAS is the ordering metric because spend without return is the finding that
 * actually costs money. Everything else on this page is context for it.
 */
#[Layout('layouts.app')]
#[Title('Marketing')]
class Marketing extends Component
{
    use InteractsWithEngine;

    public function pause(int $campaignId, string $name): void
    {
        $result = $this->bus()->manual(
            type: 'PAUSE_CAMPAIGN',
            payload: ['id' => $campaignId],
            label: "Pause \"{$name}\"",
            key: 'campaign:pause:'.$campaignId,
            userId: auth()->id(),
            area: 'marketing',
        );

        $this->toast(
            $result['ok'] ? 'Campaign paused' : 'Not applied',
            $result['message'],
            $result['ok'] ? 'pos' : 'neg',
        );

        if ($result['ok']) {
            $this->metrics()->flush();
            $this->dispatch('engine-changed');
        }
    }

    #[On('engine-changed')]
    public function refresh(): void
    {
        $this->metrics()->flush();
    }

    public function render()
    {
        $mk = $this->metrics()->marketing();

        return view('livewire.pages.marketing', [
            'mk' => $mk,
            'charts' => [
                'spend' => [
                    'data' => collect($mk['byChannel'])
                        ->map(fn ($c) => ['label' => $c['key'], 'value' => $c['spend']])
                        ->values()->all(),
                    'opts' => ['format' => 'money', 'centerLabel' => 'Total spend', 'size' => 200],
                ],
                'roas' => [
                    'data' => collect($mk['byChannel'])->sortByDesc('roas')->map(fn ($c) => [
                        'label' => $c['key'],
                        'value' => round($c['roas'], 2),
                        'note' => Fmt::moneyShort($c['spend']).' spend · '
                            .Fmt::num($c['leads']).' leads · '
                            .Fmt::pct($c['conversion'], 1).' conversion',
                        // Below 1× the campaign costs more than it returns.
                        // That is the only threshold on this page that matters.
                        'color' => $c['roas'] < 1 ? 'var(--c2)' : 'var(--c1)',
                    ])->values()->all(),
                    'opts' => ['format' => 'number'],
                ],
            ],
        ]);
    }
}
