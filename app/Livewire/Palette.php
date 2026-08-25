<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithEngine;
use App\Models\Agent;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Property;
use App\Support\TimeMachine as TimeMachineEngine;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Command palette — Ctrl-K.
 *
 * One box that searches records and routes questions. If what you typed looks
 * like a question rather than a name, the first result offers to ask it
 * instead of pretending "why is conversion falling" is a customer.
 */
class Palette extends Component
{
    use InteractsWithEngine;

    public bool $open = false;

    #[Url(except: '')]
    public string $q = '';

    public function openPalette(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->q = '';
    }

    /* ------------------------------------------------------------ actions */

    public function advanceClock(int $days): void
    {
        $summary = (new TimeMachineEngine($this->tenant()))->advance($days);

        $this->metrics()->flush();
        $found = $this->engine()->run();

        $this->close();
        $this->dispatch('engine-changed');

        $this->toast(
            "Clock advanced {$days} days",
            TimeMachineEngine::describe($summary).' · detectors re-ran, '.$found.' live findings',
            'ai',
        );
    }

    public function rerunDetectors(): void
    {
        $this->metrics()->flush();
        $found = $this->engine()->run();

        $this->close();
        $this->dispatch('engine-changed');
        $this->toast('Detectors re-run', "All 14 checked the current data · {$found} live findings", 'ai');
    }

    /** Handed to the browser, because the walkthrough drives the real UI. */
    public function startTour(): void
    {
        $this->close();
        $this->dispatch('start-tournament');
    }

    /** Looks like a question, not a name. */
    private function isQuestion(string $q): bool
    {
        return str_contains($q, '?')
            || (bool) preg_match('/^(why|what|how|which|who|when|show|top|forecast)\b/i', trim($q));
    }

    public function render()
    {
        $q = trim($this->q);
        $groups = [];

        if ($this->open && mb_strlen($q) > 2 && $this->isQuestion($q)) {
            $groups['Ask NexABI'] = [[
                'label' => "Ask: \"{$q}\"",
                'hint' => 'Enter',
                'ico' => 'ask',
                'href' => route('app.ask', ['q' => $q]),
            ]];
        }

        if ($this->open && mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';

            $groups['Leads'] = Lead::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($l) => [
                    'label' => $l->name,
                    'hint' => "{$l->status} · score {$l->score}",
                    'ico' => 'leads',
                    'href' => route('app.leads', ['q' => $l->name]),
                ])->all();

            $groups['Properties'] = Property::where(fn ($w) => $w
                ->where('title', 'like', $like)
                ->orWhere('location', 'like', $like)
                ->orWhere('ref', 'like', $like))
                ->limit(4)->get()
                ->map(fn ($p) => [
                    'label' => "{$p->title}, {$p->location}",
                    'hint' => $p->ref,
                    'ico' => 'property',
                    'href' => route('app.properties', ['q' => $p->ref]),
                ])->all();

            $groups['Customers'] = Customer::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($c) => [
                    'label' => $c->name,
                    'hint' => $c->segment,
                    'ico' => 'customers',
                    'href' => route('app.customers', ['q' => $c->name]),
                ])->all();

            $groups['Agents'] = Agent::where('name', 'like', $like)->limit(4)->get()
                ->map(fn ($a) => [
                    'label' => $a->name,
                    'hint' => $a->role,
                    'ico' => 'agent',
                    'href' => route('app.sales', ['q' => $a->name]),
                ])->all();
        }

        if ($this->open && $q === '') {
            $groups['Navigate'] = collect(Nav::ITEMS)
                ->filter(fn ($i) => isset($i['route']))
                ->take(6)
                ->map(fn ($i) => [
                    'label' => $i['label'],
                    'hint' => '',
                    'ico' => $i['ico'],
                    'href' => route($i['route']),
                ])->values()->all();

            // Things you DO rather than places you go. A palette that only
            // navigates is a nav menu with a text box on top.
            $groups['Actions'] = [
                ['label' => 'Advance the clock 7 days', 'hint' => 'simulates the week',
                    'ico' => 'clock', 'action' => 'advanceClock(7)', 'href' => null],
                ['label' => 'Advance the clock 30 days', 'hint' => 'simulates the month',
                    'ico' => 'clock', 'action' => 'advanceClock(30)', 'href' => null],
                ['label' => 'Re-run all detectors', 'hint' => 'all 14',
                    'ico' => 'reset', 'action' => 'rerunDetectors', 'href' => null],
                ['label' => 'Start Tournament Mode', 'hint' => 'guided walkthrough',
                    'ico' => 'trophy', 'action' => 'startTour', 'href' => null],
                ['label' => 'Import CSV data', 'hint' => 'Settings',
                    'ico' => 'upload', 'href' => route('app.settings')],
            ];
        }

        return view('livewire.palette', [
            'groups' => array_filter($groups, fn ($g) => count($g) > 0),
        ]);
    }
}
