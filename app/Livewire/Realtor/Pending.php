<?php

namespace App\Livewire\Realtor;

use App\Livewire\Realtor\Concerns\IsARealtor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The holding screen.
 *
 * The only page an unapproved realtor can reach. It says what happens next and
 * roughly when, because an application that ends on a blank page reads as one
 * that went nowhere — and the person then signs up twice.
 */
#[Layout('layouts.realtor')]
#[Title('Application received')]
class Pending extends Component
{
    use IsARealtor;

    public function render()
    {
        return view('livewire.realtor.pending', ['realtor' => $this->realtor()]);
    }
}
