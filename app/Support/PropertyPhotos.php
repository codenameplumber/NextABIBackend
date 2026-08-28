<?php

namespace App\Support;

use App\Models\Property;
use Illuminate\Support\Facades\Storage;

/**
 * Real photographs for the catalogue.
 *
 * Sourced from Unsplash, whose licence permits commercial use without
 * permission or attribution. Every identifier below was fetched and looked at
 * before it went in the list — two candidates that resolved fine were dropped
 * because of what they actually showed (a climate protest, and the
 * man-holding-a-tiny-house stock cliché), which is the failure you get from
 * trusting an image URL you have not opened.
 *
 * Assigned by property type so the picture matches the sale: a plot gets open
 * ground, an office gets a glass tower, a terrace gets a row of townhouses.
 * Within a type the choice is seeded from the property's own reference, so a
 * given unit always shows the same photograph and a grid is not one picture
 * repeated.
 *
 * These are illustrative, not photographs of the actual units. An agency with
 * its own photography writes it straight into `properties.images` and this is
 * never consulted; when there is no photograph at all, PropertyArt draws the
 * unit instead.
 */
final class PropertyPhotos
{
    private const CDN = 'https://images.unsplash.com/photo-';

    /**
     * Photo identifiers by scene, as categorised by eye.
     *
     * @var array<string, string[]>
     */
    /**
     * Interiors, usable behind any home.
     *
     * A real listing carries both the outside and the rooms, so mixing them in
     * is not a compromise — it is what the medium looks like. It also widens a
     * three-image pool to nine, which is what stops the same duplex appearing
     * three times on one screen.
     */
    private const HOME_INTERIORS = [
        "1600607687939-ce8a6c25118c",   // modern living room
        "1600566753086-00f18fb6b3ea",   // living room
        "1502672260266-1c1ef2d93688",   // bright flat
        "1522708323590-d24dbb6b0267",   // apartment interior
        "1493809842364-78817add7ffb",   // living room, blue sofa
        "1560448204-e02f11c3d0e2",      // open living space
        "1560185007-cde436f6a4d0",      // dining area
        "1502005229762-cf1b2da7c5d6",   // staircase, double height
    ];

    /** Exteriors, by what the building actually is. */
    private const EXTERIORS = [
        "detached" => [
            "1568605114967-8130f3a36994",   // modern house at dusk
            "1512917774080-9991f1c4c750",   // villa with pool
            "1580587771525-78b9dba3b914",   // terrace and pool
            "1600585154340-be6161a56a0c",   // dark modern house
            "1613490493576-7fde63acd811",   // modern house, pool
            "1523217582562-09d0def993a6",   // white modern build
            "1600596542815-ffad4c1539a9",   // rooftop pool
        ],
        "semi" => [
            "1600047509807-ba8f99d2cdde",   // paired modern block
            "1592595896551-12b371d546d5",   // suburban house
            "1570129477492-45c003edd2be",   // white house, lawn
            "1523217582562-09d0def993a6",
        ],
        "terrace" => [
            "1567496898669-ee935f5f647a",   // row of townhouses
            "1600047509807-ba8f99d2cdde",
            "1523217582562-09d0def993a6",
            "1592595896551-12b371d546d5",
        ],
        "bungalow" => [
            "1570129477492-45c003edd2be",
            "1592595896551-12b371d546d5",
            "1416331108676-a22ccb276e35",   // villa and courtyard
            "1568605114967-8130f3a36994",
        ],
    ];

    /**
     * Photo identifiers by scene, as categorised by eye.
     *
     * @var array<string, string[]>
     */
    public const BY_SCENE = [
        // Open ground. Never a building — a plot is a different purchase, and a
        // buyer scanning a grid should not have to read the label to see that.
        "land" => [
            "1500382017468-9049fed747ef",   // open field at sunset
            "1470071459604-3b5ec3a7fe05",   // hills and valley
            "1464822759023-fed622ff2c3b",   // wide landscape
            "1500595046743-cd271d694d30",   // farmland
        ],

        // Glass towers and worked office floors. No domestic interiors here:
        // an office is not sold on a sofa.
        "commercial" => [
            "1449824913935-59a10b8d2000",   // city street, towers
            "1486406146926-c627a92ad1ab",   // glass towers from below
            "1497366754035-f200968a6e72",   // open-plan office, glazed
            "1497366811353-6870744d04b2",   // office floor
            "1431576901776-e539bd916ba2",   // curtain wall at sunset
            "1462899006636-339e08d1844e",   // towers looking up
            "1524758631624-e2822e304c36",   // office lounge
        ],

        // A flat is sold on the room, not the block it sits in.
        "block" => self::HOME_INTERIORS,

        // The top of the market: double height, glazed, poolside.
        "penthouse" => [
            "1600607687920-4e2a09cf159d",   // dining, floor-to-ceiling glass
            "1502005229762-cf1b2da7c5d6",
            "1600596542815-ffad4c1539a9",
            "1580587771525-78b9dba3b914",
        ],

        "shortlet" => self::HOME_INTERIORS,
    ];
    /**
     * Every photograph a scene may use.
     *
     * A house type draws on its own exteriors AND the shared interiors, because
     * that is what a listing looks like — and because a pool of three repeats
     * itself three times on one screen of a catalogue this size.
     *
     * @return string[]
     */
    public static function pool(string $scene): array
    {
        if (isset(self::EXTERIORS[$scene])) {
            /**
             * Exteriors weighted to roughly two in three.
             *
             * An even mix put a living room on most of the duplexes, and for a
             * house the outside is the more informative shot — it is what tells
             * a buyer whether it is a terrace or a standalone. Interiors are
             * still in, because a listing that never shows a room is a listing
             * people distrust; they are just not the majority.
             */
            return array_merge(
                self::EXTERIORS[$scene],
                self::EXTERIORS[$scene],
                array_slice(self::HOME_INTERIORS, 0, 4),
            );
        }

        return self::BY_SCENE[$scene] ?? self::BY_SCENE['block'];
    }

    /**
     * A stable photograph for this property, sized for the card.
     *
     * `fit=crop` and an explicit size so the CDN does the resizing rather than
     * the browser downloading a 4000px original to draw it at 600.
     */
    public static function for(?Property $property, int $w = 800, int $h = 520): ?string
    {
        // An agency that only wants its own photography on its own listings
        // turns this off; anything it has not photographed yet falls through to
        // the drawing, which can never show the wrong building.
        if (! $property || ! config('nexabi.stock_photos', true)) {
            return null;
        }

        $scene = PropertyArt::SCENES[$property->type] ?? 'block';
        $pool = self::pool($scene);

        if (! $pool) {
            return null;
        }

        // Seeded from the reference: the same unit always shows the same
        // photograph, and neighbouring cards do not repeat.
        $id = $pool[abs(crc32(($property->ref ?? '').':'.$scene)) % count($pool)];

        return self::CDN.$id.'?auto=format&fit=crop&w='.$w.'&h='.$h.'&q=70';
    }

    /**
     * Turn a stored `properties.images` entry into something a browser can load.
     *
     * The column holds two genuinely different kinds of value and always has:
     * a full URL, from an agency pointing at photography it hosts elsewhere,
     * and a path under public/, from a file uploaded here. Putting either
     * straight into src worked for the first and produced a broken image for
     * the second, because a relative path resolves against the CURRENT page —
     * so the same upload rendered on /properties and 404'd on /properties/NX-1.
     *
     * Anything with a scheme is left exactly as it was found. A protocol-
     * relative URL and a data URI are both already absolute and are left alone
     * too. Everything else is a local file and goes through asset().
     */
    public static function resolve(?string $image): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        // Already absolute: an external URL, a protocol-relative one, or an
        // inline data URI. None of these want a host prepended.
        if (preg_match('#^(https?:)?//#i', $image) || str_starts_with($image, 'data:')) {
            return $image;
        }

        $image = ltrim($image, '/');

        /**
         * A relative path means "we stored this" — but WHERE depends on the
         * environment, not on the row.
         *
         * The database holds the same value on a laptop and on Laravel Cloud;
         * only this line differs. That is deliberate: it means a dataset can
         * move between the two without rewriting a single `images` column, and
         * switching an existing deployment to object storage is a config change
         * plus a file copy rather than a data migration.
         */
        if ($disk = ImageStore::disk()) {
            return Storage::disk($disk)->url($image);
        }

        return asset($image);
    }
}
