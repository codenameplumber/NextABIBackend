<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Support\Purchase;

/**
 * Shared plumbing for the four portal pages.
 *
 * The customer is resolved from the signed-in user, never from a request
 * parameter — a portal where the account is addressable is a portal where
 * someone reads a stranger's payment schedule.
 */
trait ServesTheClient
{
    public function customer(): Customer
    {
        return once(fn () => Customer::with('agent')->findOrFail(auth()->user()->customer_id));
    }

    public function purchase(): Purchase
    {
        return once(fn () => Purchase::for($this->customer()));
    }

    public function firstName(): string
    {
        return $this->customer()->firstName();
    }
}
