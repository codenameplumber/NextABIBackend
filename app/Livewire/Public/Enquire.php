<?php

namespace App\Livewire\Public;

use App\Models\Property;
use App\Models\Tenant;
use App\Platform\Realtors;
use App\Platform\Registrations;
use App\Support\Nigeria;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The questionnaire.
 *
 * What a realtor sends and what an advert points at. It collects enough to
 * follow someone up properly and stops there — no password, no account.
 * Somebody answering an advert at eleven at night is not ready to choose
 * credentials, and asking them to is how a capture form gets a two percent
 * completion rate.
 *
 * ?ref= carries the realtor's code, so the enquiry lands in their own list.
 */
#[Layout('components.layouts.marketing')]
#[Title('Tell us what you are looking for')]
class Enquire extends Component
{
    #[Url(as: 'ref', keep: true)]
    public ?string $ref = null;

    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $state = '';
    public string $lga = '';
    public string $income_bracket = '';
    public string $occupation = '';
    public string $heard_about_us = '';
    public string $wants_location = '';
    public string $wants_type = '';
    public string $budget = '';

    public bool $sent = false;

    public function updatedState(): void
    {
        // The pair has to stay consistent — a Lagos buyer cannot live in Bwari.
        $this->lga = '';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:32'],
            'state' => ['required', 'string'],
            'lga' => ['required', 'string'],
            'income_bracket' => ['required', 'string'],
            'occupation' => ['required', 'string'],
            'heard_about_us' => ['required', 'string'],
            'wants_location' => ['nullable', 'string', 'max:80'],
            'wants_type' => ['nullable', 'string', 'max:60'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone.required' => 'A phone number, so somebody can actually reach you.',
            'lga.required' => 'Pick your local government.',
            'heard_about_us.required' => 'Let us know how you found us.',
        ];
    }

    public function submit(): void
    {
        $data = $this->validate();

        if (! Nigeria::isLga($this->state, $this->lga)) {
            $this->addError('lga', 'That local government is not in the state you picked.');

            return;
        }

        app(Registrations::class)->lead(Tenant::first(), $data, $this->ref);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.public.enquire', [
            'realtor' => app(Realtors::class)->findByCode($this->ref),
            'states' => Nigeria::stateNames(),
            'lgas' => Nigeria::lgas($this->state),
            'incomes' => Nigeria::INCOME_BRACKETS,
            'occupations' => Nigeria::OCCUPATIONS,
            'sources' => Nigeria::REFERRAL_SOURCES,
            'locations' => Property::query()->onMarket()
                ->distinct()->orderBy('location')->pluck('location'),
            'types' => Property::query()->onMarket()
                ->distinct()->orderBy('type')->pluck('type'),
        ]);
    }
}
