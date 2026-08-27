<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use App\Models\Lead;
use App\Models\Tenant;
use App\Platform\Registrations;
use App\Support\Nigeria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A realtor's own leads.
 *
 * Two ways in: the questionnaire link, which files itself, and this form, for
 * the conversation that happened at a wedding. Both land in the same list, so
 * a realtor has one place to look rather than a screen plus a notebook.
 *
 * Scoped to the signed-in realtor with no way to widen it.
 */
#[Layout('layouts.realtor')]
#[Title('My leads')]
class Leads extends Component
{
    use IsARealtor, WithPagination;

    public bool $adding = false;

    public string $name = '';
    public string $phone = '';
    public string $email = '';
    public string $state = '';
    public string $lga = '';
    public string $income_bracket = '';
    public string $occupation = '';
    public string $heard_about_us = 'realtor';
    public string $wants_location = '';
    public string $budget = '';

    public function updatedState(): void
    {
        $this->lga = '';
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:190'],
            'state' => ['required', 'string'],
            'lga' => ['required', 'string'],
            'income_bracket' => ['required', 'string'],
            'occupation' => ['required', 'string'],
            'heard_about_us' => ['required', 'string'],
            'wants_location' => ['nullable', 'string', 'max:80'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! Nigeria::isLga($this->state, $this->lga)) {
            $this->addError('lga', 'That local government is not in the state you picked.');

            return;
        }

        app(Registrations::class)->lead(Tenant::first(), $data, $this->realtor()->code);

        $this->reset(['name', 'phone', 'email', 'state', 'lga', 'income_bracket',
            'occupation', 'wants_location', 'budget', 'adding']);

        $this->toast('Lead recorded', $data['name'].' is on your list.', 'pos');
    }

    public function render()
    {
        return view('livewire.realtor.leads', [
            'realtor' => $this->realtor(),
            'rows' => Lead::where('assigned_agent_id', $this->realtor()->id)
                ->orderByDesc('created_at')->paginate(15),
            'states' => Nigeria::stateNames(),
            'lgas' => Nigeria::lgas($this->state),
            'incomes' => Nigeria::INCOME_BRACKETS,
            'occupations' => Nigeria::OCCUPATIONS,
            'sources' => Nigeria::REFERRAL_SOURCES,
        ]);
    }
}
