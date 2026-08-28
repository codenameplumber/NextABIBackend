<?php

namespace Tests\Feature;

use App\Livewire\Pages\Commitments;
use App\Livewire\Pages\Marketing;
use App\Livewire\Pages\Materials;
use App\Livewire\Pages\Properties;
use App\Models\Agent;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Property;
use App\Models\PromotionalMaterial;
use App\Support\ImageStore;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The screens an operator actually works in.
 *
 * Everything here is a write path reached through a Livewire component rather
 * than through a service — which is the half that render checks cannot see. A
 * page can return 200 with a form on it that saves nothing.
 */
class OperatorToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->staff());
    }

    /* ====================================================== marketing */

    public function test_marketing_that_leaves_no_click_can_still_be_recorded(): void
    {
        // A billboard produces buyers and no tracking data at all. Before it
        // can be recorded, the return on it is not merely unknown — it is
        // uncomputable, because the spend is nowhere in the system.
        Livewire::test(Marketing::class)
            ->call('addRecord')
            ->set('name', 'Billboard — Lekki-Epe Expressway')
            ->set('channel', 'Offline')
            ->set('source', 'Alpha Outdoor')
            ->set('spend', '2500000')
            ->set('startAt', now()->subMonths(2)->toDateString())
            ->set('expectedLeads', '120')
            ->call('save')
            ->assertHasNoErrors();

        $record = Campaign::where('name', 'Billboard — Lekki-Epe Expressway')->firstOrFail();

        $this->assertTrue($record->manual, 'A hand-entered record has to be distinguishable.');
        $this->assertSame(120, $record->expected_leads);
        $this->assertSame(2500000.0, $record->spend);
    }

    public function test_an_uncounted_record_is_not_the_same_as_a_failed_one(): void
    {
        $record = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Radio — Wazobia FM',
            'channel' => 'Radio', 'source' => 'Wazobia', 'spend' => 900000,
            'start_at' => now()->subMonth(), 'status' => 'active', 'manual' => true,
            'expected_leads' => 80,
        ]);

        // Nobody has counted it yet. That is a chore, not a finding, and the
        // two must not collapse into the same zero — the screen reads
        // isCounted() to decide between "not counted yet" and a real figure.
        $this->assertFalse($record->isCounted());
        $this->assertNull($record->actual_leads);

        Livewire::test(Marketing::class)
            ->call('count', $record->id)
            ->set('actualLeads', '0')
            ->call('saveCount')
            ->assertHasNoErrors();

        $record->refresh();

        // Now it IS a finding: zero delivered against eighty promised.
        $this->assertTrue($record->isCounted());
        $this->assertSame(0, $record->actual_leads);
        $this->assertSame(0.0, $record->deliveryRate());
    }

    public function test_delivery_is_measured_against_what_was_promised(): void
    {
        $record = Campaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Church announcement — Ikeja',
            'channel' => 'Community', 'source' => 'Parish', 'spend' => 150000,
            'start_at' => now()->subMonth(), 'status' => 'active', 'manual' => true,
            'expected_leads' => 40, 'actual_leads' => 30,
        ]);

        // Forty leads is a success or a failure depending entirely on whether
        // you were sold thirty or four hundred. Storing only the outcome makes
        // that unanswerable.
        $this->assertSame(0.75, $record->deliveryRate());
        $this->assertSame(5000.0, $record->costPerLead());
    }

    /* ==================================================== commitments */

    public function test_the_queue_refuses_to_approve_without_a_realtor_and_the_drawer_says_why(): void
    {
        $blocked = Deal::where('status', 'pending')
            ->whereHas('customer', fn ($q) => $q->whereNull('agent_id'))
            ->firstOrFail();

        Livewire::test(Commitments::class)
            ->call('open', $blocked->id)
            // The blocker and its fix have to be in the same place. A correct
            // error message on another screen is a round trip per row.
            ->assertSee('Assign a realtor to this investor first')
            ->call('approve', $blocked->id);

        $this->assertTrue(
            $blocked->fresh()->isPending(),
            'A commitment with no realtor was approved — commission would have no owner.',
        );
    }

    public function test_attaching_a_realtor_from_the_drawer_clears_the_block(): void
    {
        $blocked = Deal::where('status', 'pending')
            ->whereHas('customer', fn ($q) => $q->whereNull('agent_id'))
            ->firstOrFail();

        $realtor = Agent::query()->approved()->firstOrFail();

        Livewire::test(Commitments::class)
            ->call('open', $blocked->id)
            ->set('assignTo.'.$blocked->id, $realtor->id)
            ->call('assign', $blocked->id);

        $blocked->refresh();

        $this->assertTrue($blocked->customer->isAssigned());
        $this->assertNull($blocked->approvalBlocker(), 'The block should be gone.');
    }

    public function test_the_realtor_picker_searches_by_code_not_just_name(): void
    {
        $blocked = Deal::where('status', 'pending')
            ->whereHas('customer', fn ($q) => $q->whereNull('agent_id'))
            ->firstOrFail();

        $realtor = Agent::query()->approved()->whereNotNull('code')->firstOrFail();

        // The code is what an operator has written on the form in front of
        // them. A select box that cannot be searched by it is unusable at 118
        // realtors, which is the whole reason this control exists.
        Livewire::test(Commitments::class)
            ->call('open', $blocked->id)
            ->set('realtorSearch', $realtor->code)
            ->assertSee($realtor->name);
    }

    public function test_the_queue_can_be_searched_by_investor(): void
    {
        $c = Deal::with('customer')->where('status', 'pending')->firstOrFail();

        Livewire::test(Commitments::class)
            ->set('filter', 'all')
            ->set('search', $c->customer->name)
            ->assertSee($c->customer->name);
    }

    /* ===================================================== properties */

    public function test_a_property_can_be_listed_with_an_uploaded_photograph(): void
    {
        $before = Property::count();

        Livewire::test(Properties::class)
            ->call('addListing')
            ->set('f_title', 'Test Duplex at Ikate')
            ->set('f_type', 'Detached Duplex')
            ->set('f_location', 'Lekki')
            ->set('f_bedrooms', '4')
            ->set('f_price', '85000000')
            ->set('f_commission', '5')
            ->set('f_minMonths', '6')
            ->set('f_maxMonths', '24')
            ->set('uploads', [UploadedFile::fake()->image('unit.jpg', 800, 600)])
            ->call('saveListing')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, Property::count());

        $p = Property::where('title', 'Test Duplex at Ikate')->firstOrFail();

        $this->assertNotEmpty($p->images);
        $stored = $p->images[0];

        // Written under public/ rather than onto the `public` DISK, which
        // needs storage:link and 404s silently on any machine that skipped it.
        $this->assertStringStartsWith('img/properties/', $stored);
        $this->assertFileExists(public_path($stored));

        ImageStore::forget($stored, 'img/properties');
    }

    public function test_a_listing_accepts_hosted_photography_as_well_as_uploads(): void
    {
        // An agency with 700 units already shot and on a CDN should not have
        // to re-upload every one of them to use this product.
        Livewire::test(Properties::class)
            ->call('addListing')
            ->set('f_title', 'Test Plot at Gbagada')
            ->set('f_type', 'Land')
            ->set('f_location', 'Gbagada')
            ->set('f_price', '30000000')
            ->set('imageUrl', 'https://cdn.example.test/plot.jpg')
            ->call('addImageUrl')
            ->call('saveListing')
            ->assertHasNoErrors();

        $p = Property::where('title', 'Test Plot at Gbagada')->firstOrFail();

        $this->assertSame(['https://cdn.example.test/plot.jpg'], $p->images);
    }

    public function test_stored_paths_and_remote_urls_both_resolve_to_something_loadable(): void
    {
        $resolve = \App\Support\PropertyPhotos::resolve(...);

        // A relative path resolves against the CURRENT page unless it is run
        // through asset(), so the same upload would render on /properties and
        // 404 on /properties/NX-1.
        $this->assertSame(asset('img/properties/x.jpg'), $resolve('img/properties/x.jpg'));
        $this->assertSame(asset('img/properties/x.jpg'), $resolve('/img/properties/x.jpg'));

        // Anything already absolute is left exactly as it was found.
        $this->assertSame('https://cdn.test/a.jpg', $resolve('https://cdn.test/a.jpg'));
        $this->assertSame('//cdn.test/a.jpg', $resolve('//cdn.test/a.jpg'));

        $this->assertNull($resolve(''));
        $this->assertNull($resolve(null));
    }

    public function test_a_commission_rate_typed_as_a_percentage_cannot_pay_out_the_building(): void
    {
        Livewire::test(Properties::class)
            ->call('addListing')
            ->set('f_title', 'Test Rate Guard')
            ->set('f_type', 'Apartment')
            ->set('f_location', 'Lekki')
            ->set('f_price', '50000000')
            // Typed as "12" meaning twelve percent. Stored raw that is 12.0,
            // which pays a realtor twelve times the property value.
            ->set('f_commission', '12')
            ->call('saveListing')
            ->assertHasNoErrors();

        $p = Property::where('title', 'Test Rate Guard')->firstOrFail();

        $this->assertSame(0.12, round((float) $p->commission_rate, 3));
        $this->assertLessThanOrEqual(Property::MAX_COMMISSION_RATE, (float) $p->commission_rate);
    }

    /* ====================================================== materials */

    public function test_a_flyer_needs_artwork_and_a_message_needs_words(): void
    {
        // A flyer with no file is a title, and it reaches a realtor's screen
        // as a broken image.
        Livewire::test(Materials::class)
            ->call('add')
            ->set('kind', 'flyer')
            ->set('title', 'Launch flyer')
            ->call('save')
            ->assertHasErrors('asset');

        Livewire::test(Materials::class)
            ->call('add')
            ->set('kind', 'copy')
            ->set('title', 'WhatsApp opener')
            ->call('save')
            ->assertHasErrors('body');
    }

    public function test_a_flyer_can_be_uploaded_and_is_previewable(): void
    {
        Livewire::test(Materials::class)
            ->call('add')
            ->set('kind', 'flyer')
            ->set('title', 'Test launch flyer')
            ->set('asset', UploadedFile::fake()->image('flyer.png', 1080, 1350))
            ->call('save')
            ->assertHasNoErrors();

        $m = PromotionalMaterial::where('title', 'Test launch flyer')->firstOrFail();

        $this->assertTrue($m->hasFile());
        $this->assertTrue($m->isPreviewable(), 'A PNG flyer should render inline.');
        $this->assertStringStartsWith('downloads/materials/', $m->file_path);
        $this->assertFileExists(public_path($m->file_path));

        ImageStore::forget($m->file_path, 'downloads/materials');
    }

    public function test_deleting_a_material_takes_its_file_with_it(): void
    {
        Livewire::test(Materials::class)
            ->call('add')
            ->set('kind', 'image')
            ->set('title', 'Test social square')
            ->set('asset', UploadedFile::fake()->image('square.jpg', 1080, 1080))
            ->call('save');

        $m = PromotionalMaterial::where('title', 'Test social square')->firstOrFail();
        $path = $m->file_path;
        $this->assertFileExists(public_path($path));

        Livewire::test(Materials::class)->call('destroy', $m->id);

        // A withdrawn material can come back; a deleted one cannot, so leaving
        // the asset behind is a folder that grows forever.
        $this->assertNull(PromotionalMaterial::find($m->id));
        $this->assertFileDoesNotExist(public_path($path));
    }

    public function test_the_store_refuses_to_delete_outside_its_own_folder(): void
    {
        $canary = public_path('img/properties/.canary');
        @mkdir(dirname($canary), 0o755, true);
        file_put_contents($canary, 'x');

        // A tampered form value must not be able to unlink anything it likes.
        ImageStore::forget('../../.env', 'img/properties');
        ImageStore::forget('downloads/materials/../../img/properties/.canary', 'img/properties');

        $this->assertFileExists($canary);
        @unlink($canary);
    }

    /* ============================================ ephemeral hosts */

    /**
     * On a host whose filesystem does not survive a deploy, writing into
     * public/ loses every uploaded photograph at the next release — silently,
     * with broken images on listings somebody added last week as the only
     * symptom. Setting a disk has to move the write AND the URL together.
     */
    public function test_uploads_go_to_a_durable_disk_when_one_is_configured(): void
    {
        \Illuminate\Support\Facades\Storage::fake('uploads');
        config(['nexabi.upload_disk' => 'uploads']);

        $path = ImageStore::put(
            UploadedFile::fake()->image('unit.jpg', 800, 600),
            'img/properties',
        );

        // The value written to the database is the same relative path it would
        // be locally — that is what lets one dataset work in both places.
        $this->assertStringStartsWith('img/properties/', $path);
        $this->assertFileDoesNotExist(public_path($path), 'It still wrote into public/.');
        \Illuminate\Support\Facades\Storage::disk('uploads')->assertExists($path);

        // And the URL has to follow the bytes, or the row points at nothing.
        $this->assertSame(
            \Illuminate\Support\Facades\Storage::disk('uploads')->url($path),
            \App\Support\PropertyPhotos::resolve($path),
        );

        ImageStore::forget($path, 'img/properties');
        \Illuminate\Support\Facades\Storage::disk('uploads')->assertMissing($path);
    }

    public function test_a_remote_url_is_untouched_whichever_disk_is_configured(): void
    {
        // Agency photography hosted elsewhere must not be rewritten into a
        // bucket path just because a disk happens to be set.
        foreach ([null, 'uploads'] as $disk) {
            \Illuminate\Support\Facades\Storage::fake('uploads');
            config(['nexabi.upload_disk' => $disk]);

            $this->assertSame(
                'https://cdn.test/a.jpg',
                \App\Support\PropertyPhotos::resolve('https://cdn.test/a.jpg'),
            );
        }
    }
}
