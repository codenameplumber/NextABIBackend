<?php

namespace Tests\Feature;

use App\Livewire\Portal\Commit;
use App\Models\Deal;
use App\Models\Property;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CommitFlowTest extends TestCase
{
    private function buyer(): User
    {
        return User::where('email', 'client@example.com')->firstOrFail();
    }

    private function freeProperty(): Property
    {
        $held = Deal::where('customer_id', $this->buyer()->customer_id)->pluck('property_id');

        return Property::query()->onMarket()->whereNotIn('id', $held)->firstOrFail();
    }

    public function test_the_commit_page_renders_with_its_term_picker(): void
    {
        $p = $this->freeProperty();

        Livewire::actingAs($this->buyer())
            ->test(Commit::class, ['ref' => $p->ref])
            ->assertOk()
            ->assertSee($p->title)
            ->assertSee('Commit to this property');
    }

    public function test_pressing_commit_without_agreeing_explains_why(): void
    {
        $p = $this->freeProperty();

        Livewire::actingAs($this->buyer())
            ->test(Commit::class, ['ref' => $p->ref])
            ->call('submitCommitment')
            ->assertHasErrors('agreed');
    }

    public function test_pressing_commit_creates_a_pending_commitment_and_holds_the_unit(): void
    {
        $p = $this->freeProperty();
        $before = Deal::where('customer_id', $this->buyer()->customer_id)->count();

        Livewire::actingAs($this->buyer())
            ->test(Commit::class, ['ref' => $p->ref])
            ->set('agreed', true)
            ->set('months', 12)
            ->call('submitCommitment')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.overview'));

        $this->assertSame(
            $before + 1,
            Deal::where('customer_id', $this->buyer()->customer_id)->count(),
            'Pressing commit did not create a commitment.',
        );

        $deal = Deal::where('customer_id', $this->buyer()->customer_id)
            ->where('property_id', $p->id)->firstOrFail();

        $this->assertTrue($deal->isPending());
        $this->assertSame(12, (int) $deal->duration_months);
        $this->assertSame('reserved', $p->fresh()->status, 'The unit was not held.');
    }

    public function test_the_term_picker_changes_the_monthly_figure(): void
    {
        $p = $this->freeProperty();
        $options = $p->durationOptions();
        $shortest = min($options);
        $longest = max($options);

        Livewire::actingAs($this->buyer())
            ->test(Commit::class, ['ref' => $p->ref])
            ->set('months', $longest)
            ->assertSee(\App\Support\Fmt::money($p->instalmentFor($longest)))
            ->set('months', $shortest)
            ->assertSee(\App\Support\Fmt::money($p->instalmentFor($shortest)));
    }
}
