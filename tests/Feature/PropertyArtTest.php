<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Support\PropertyArt;
use App\Support\Vocab;
use Tests\TestCase;

/**
 * The picture on a property card.
 *
 * Two things matter here and neither is aesthetic. A buyer scanning a grid has
 * to be able to tell a surveyed plot from a duplex from an office tower without
 * reading a label — so the scenes have to actually differ. And every colour has
 * to come from a token, because a hex typed into a drawing is a hex that only
 * works in one theme, and this product ships both.
 */
class PropertyArtTest extends TestCase
{
    private function property(string $type, string $ref = 'NX-TEST'): Property
    {
        return new Property([
            'ref' => $ref,
            'type' => $type,
            'location' => 'Lekki',
            'bedrooms' => 3,
        ]);
    }

    public function test_every_sale_type_the_simulator_produces_has_a_scene(): void
    {
        // Driven off Vocab rather than a list written here — adding a tenth
        // property type should fail this test, not silently fall back to a
        // block of flats.
        foreach (Vocab::PROP_TYPES as $t) {
            $this->assertArrayHasKey(
                $t['name'],
                PropertyArt::SCENES,
                "No scene defined for [{$t['name']}] — it would draw as a generic block.",
            );
        }
    }

    public function test_the_nine_types_draw_nine_different_scenes(): void
    {
        $scenes = [];

        foreach (array_keys(PropertyArt::SCENES) as $type) {
            $scenes[] = PropertyArt::for($this->property($type))->scene();
        }

        $this->assertCount(count($scenes), array_unique($scenes),
            'Two sale types share a drawing — a plot must never look like a house.');
    }

    public function test_land_is_never_drawn_as_a_building(): void
    {
        $land = PropertyArt::for($this->property('Land'));

        $this->assertTrue($land->isLand());

        $svg = $land->svg();

        // A plot is sold on its boundary and its beacons, not on a roofline.
        $this->assertStringContainsString('var(--art-beacon)', $svg);
        $this->assertStringContainsString('stroke-dasharray', $svg);
        $this->assertStringNotContainsString('var(--art-door)', $svg,
            'Land has no front door.');
    }

    public function test_a_commercial_unit_is_drawn_as_one(): void
    {
        $office = PropertyArt::for($this->property('Office Space'));

        $this->assertTrue($office->isCommercial());
        // Continuous glazing bands are what make a building read as commercial.
        $this->assertStringContainsString('var(--art-glass)', $office->svg());
    }

    public function test_the_same_unit_always_looks_like_itself(): void
    {
        $a = PropertyArt::for($this->property('Detached Duplex', 'NX-4021'))->svg();
        $b = PropertyArt::for($this->property('Detached Duplex', 'NX-4021'))->svg();

        // A flat that redraws itself on every page load reads as a broken page.
        $this->assertSame($a, $b);
    }

    public function test_two_units_of_one_type_do_not_look_identical(): void
    {
        $a = PropertyArt::for($this->property('Apartment', 'NX-1001'))->svg();
        $b = PropertyArt::for($this->property('Apartment', 'NX-1002'))->svg();

        $this->assertNotSame($a, $b,
            'A grid of one type should not be nine copies of one picture.');
    }

    public function test_no_colour_is_hardcoded_anywhere_in_the_artwork(): void
    {
        foreach (array_keys(PropertyArt::SCENES) as $type) {
            $svg = PropertyArt::for($this->property($type, 'NX-'.crc32($type)))->svg();

            // A hex in a drawing is a hex that only works in one theme.
            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,8}\b/',
                $svg,
                "[{$type}] carries a literal colour — it must come from a token.",
            );

            $this->assertMatchesRegularExpression('/var\(--art-/', $svg,
                "[{$type}] should be painted from the art palette.");
        }
    }

    /* ---------------------------------------------------------- photographs */

    public function test_every_scene_has_photographs_and_they_match_the_sale(): void
    {
        foreach (PropertyArt::SCENES as $type => $scene) {
            $pool = \App\Support\PropertyPhotos::pool($scene);

            $this->assertNotEmpty($pool, "[{$type}] has no photographs.");

            // A pool of three repeats itself three times on one screen of a
            // catalogue this size, so homes draw on interiors as well.
            if (in_array($scene, ['detached', 'semi', 'terrace', 'bungalow'], true)) {
                $this->assertGreaterThanOrEqual(8, count($pool),
                    "[{$type}] has too few photographs to fill a page without repeating.");
            }
        }
    }

    public function test_land_and_offices_never_borrow_a_living_room(): void
    {
        $homes = \App\Support\PropertyPhotos::pool('block');

        foreach (['land', 'commercial'] as $scene) {
            $overlap = array_intersect(\App\Support\PropertyPhotos::pool($scene), $homes);

            // A plot is not sold on a sofa, and neither is an office floor.
            $this->assertSame([], $overlap,
                "[{$scene}] is using a domestic interior.");
        }
    }

    public function test_a_photograph_is_stable_for_a_unit_and_varies_between_them(): void
    {
        $a = \App\Support\PropertyPhotos::for($this->property('Apartment', 'NX-7001'));
        $again = \App\Support\PropertyPhotos::for($this->property('Apartment', 'NX-7001'));
        $b = \App\Support\PropertyPhotos::for($this->property('Apartment', 'NX-7002'));

        $this->assertSame($a, $again);
        $this->assertNotNull($a);
        $this->assertNotSame($a, $b);
    }

    public function test_the_photograph_is_asked_for_at_the_size_it_is_drawn(): void
    {
        $url = \App\Support\PropertyPhotos::for($this->property('Land', 'NX-7100'));

        // Without an explicit size the browser pulls a 4000px original to draw
        // it at 600 — twelve of those is the whole page weight.
        $this->assertStringContainsString('w=800', $url);
        $this->assertStringContainsString('fit=crop', $url);
    }

    public function test_a_real_photograph_wins_over_the_drawing(): void
    {
        $property = Property::query()->onMarket()->firstOrFail();
        $property->forceFill(['images' => ['https://example.test/unit.jpg']])->save();

        // The drawing is the fallback, not the plan. An agency with photographs
        // of its own stock should see those.
        $html = $this->renderArt($property->fresh());

        $this->assertStringContainsString('https://example.test/unit.jpg', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_without_its_own_photograph_it_uses_a_matched_one(): void
    {
        $property = Property::query()->onMarket()->firstOrFail();
        $property->forceFill(['images' => null])->save();

        // Second in the order of preference: a curated photograph for the sale
        // type, not the drawing.
        $html = $this->renderArt($property->fresh());

        $this->assertStringContainsString('images.unsplash.com', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_with_stock_photography_off_it_falls_back_to_the_drawing(): void
    {
        // An agency that only wants its own photography on its own listings.
        config(['nexabi.stock_photos' => false]);

        $property = Property::query()->onMarket()->firstOrFail();
        $property->forceFill(['images' => null])->save();

        $html = $this->renderArt($property->fresh());

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString($property->ref, $html);
        // Labelled as well as drawn, and readable to a screen reader.
        $this->assertStringContainsString('role="img"', $html);
        $this->assertStringContainsString('aria-label', $html);
    }

    /**
     * Render the component on its own, without needing a route for it.
     *
     * Named renderArt rather than blade(): Laravel s TestCase already has a
     * protected blade(), and shadowing it with a private one is a fatal error
     * before a single test runs.
     */
    private function renderArt(Property $property): string
    {
        return (string) \Illuminate\Support\Facades\Blade::render(
            '<x-property-art :property="$property" />',
            ['property' => $property],
        );
    }
}
