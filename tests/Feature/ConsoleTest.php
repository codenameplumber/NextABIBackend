<?php

namespace Tests\Feature;

use App\Engine\InsightEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\ActionLog;
use App\Models\Lead;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The console: every page renders, and the two paths that mutate data work.
 */
class ConsoleTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'dashboard' => ['/app'],
            'insights' => ['/app/insights'],
            'ask' => ['/app/ask'],
            'sales' => ['/app/sales'],
            'sales · funnel' => ['/app/sales?view=funnel'],
            'sales · agents' => ['/app/sales?view=agents'],
            'leads' => ['/app/leads'],
            'leads · stale' => ['/app/leads?filter=stale'],
            'properties' => ['/app/properties'],
            'properties · slow' => ['/app/properties?filter=stale'],
            'marketing' => ['/app/marketing'],
            'customers' => ['/app/customers'],
            'finance' => ['/app/finance'],
            'finance · overdue' => ['/app/finance?filter=overdue'],
            'feedback' => ['/app/feedback'],
            'analytics' => ['/app/analytics'],
            'analytics · efficiency' => ['/app/analytics?lens=efficiency'],
            'analytics · mix' => ['/app/analytics?lens=mix'],
            'reports' => ['/app/reports'],
            'reports · finance' => ['/app/reports?report=finance'],
            'reports · marketing' => ['/app/reports?report=marketing'],
            'reports · pipeline' => ['/app/reports?report=pipeline'],
            'reports · sales' => ['/app/reports?report=sales'],
            'activity' => ['/app/activity'],
            'settings' => ['/app/settings'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_console_page_renders(string $url): void
    {
        InsightEngine::for($this->tenant)->run();

        $this->actingAs($this->staff())->get($url)->assertOk();
    }

    public function test_the_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('NexABI');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_signing_in_lands_staff_on_the_dashboard(): void
    {
        $this->post('/login', [
            'email' => 'ada@anchorstone.ng',
            'password' => 'password',
        ])->assertRedirect(route('app.dashboard'));
    }

    public function test_signing_in_lands_a_buyer_in_the_portal(): void
    {
        $this->post('/login', [
            'email' => 'client@example.com',
            'password' => 'password',
        ])->assertRedirect(route('portal.overview'));
    }

    public function test_a_wrong_password_is_refused(): void
    {
        $this->post('/login', [
            'email' => 'ada@anchorstone.ng',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_role_switcher_re_ranks_without_filtering(): void
    {
        InsightEngine::for($this->tenant)->run();
        $engine = InsightEngine::for($this->tenant);

        $ceo = collect($engine->feed('ceo', 20))->pluck('id');
        $finance = collect($engine->feed('finance', 20))->pluck('id');

        $this->assertSame($ceo->count(), $finance->count(), 'Switching role changed how many findings exist.');
        $this->assertNotSame(
            $ceo->all(),
            $finance->all(),
            'Switching role produced an identical ordering — the weights are doing nothing.',
        );
    }

    public function test_importing_a_csv_moves_the_metrics_and_re_runs_detection(): void
    {
        $before = Lead::count();

        $csv = implode("\n", [
            'name,email,source,status,score,budget,location,type',
            'Test Importone,one@example.com,Referral,qualified,78,120000000,Lekki,Apartment',
            'Test Importtwo,two@example.com,Instagram,contacted,54,85000000,Ajah,Terraced Duplex',
        ]);

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Pages\Settings::class)
            ->set('csv', UploadedFile::fake()->createWithContent('leads.csv', $csv))
            ->call('import')
            ->assertHasNoErrors();

        $this->assertSame($before + 2, Lead::count());
        $this->assertNotNull(Lead::where('name', 'Test Importone')->first());
        $this->assertTrue((bool) Lead::where('name', 'Test Importone')->first()->imported);
    }

    public function test_generated_leads_are_real_rows_the_detectors_can_see(): void
    {
        $before = Lead::count();

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Pages\Settings::class)
            ->call('generate', 40);

        $this->assertSame($before + 40, Lead::count());
    }

    public function test_applying_from_the_dashboard_logs_and_re_detects(): void
    {
        InsightEngine::for($this->tenant)->run();

        $feed = InsightEngine::for($this->tenant)->feed('ceo', 8);
        $top = $feed[0] ?? null;
        $this->assertNotNull($top);

        $rec = $top->primaryRecommendation();
        if (! $rec || $rec->action_type === 'NAVIGATE') {
            $this->markTestSkipped('The top finding has no executable primary recommendation.');
        }

        $before = ActionLog::count();

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\Pages\Dashboard::class)
            ->call('applyTop', $rec->id);

        $this->assertGreaterThan($before, ActionLog::count());
    }

    public function test_dismissing_a_finding_takes_it_off_the_feed(): void
    {
        InsightEngine::for($this->tenant)->run();

        $insight = InsightEngine::for($this->tenant)->live()[0];

        Livewire::actingAs($this->staff())
            ->test(\App\Livewire\InsightCard::class, ['insight' => $insight])
            ->call('dismiss');

        $this->assertNotNull($insight->fresh()->dismissed_at);
    }

    public function test_the_scheduled_detector_command_runs(): void
    {
        $this->artisan('nexabi:detect')->assertSuccessful();

        $this->assertNotNull($this->tenant->fresh()->detectors_ran_at);
    }
}
