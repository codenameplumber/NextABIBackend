<?php

namespace App\Support;

use App\Models\Property;

/**
 * Artwork for a property, built from its own attributes.
 *
 * Nine property types that a Lagos agency actually sells, drawn as nine
 * genuinely different pictures — a surveyed plot does not look like a
 * duplex, and a glass office tower does not look like a bungalow. Storeys,
 * window lighting, roof pitch, vegetation and sky all vary, seeded from the
 * property's own reference so a given unit always looks like itself.
 *
 * WHY NOT PHOTOGRAPHS. A real listing carries a photograph of the actual
 * building. This codebase cannot: stock photography of somebody else's
 * buildings, presented as this agency's stock, is a licensing problem and a
 * lie about what the system knows. The moment there are real photographs,
 * `properties.images` holds them and the component prefers them over this —
 * so this is the fallback, not the plan.
 *
 * Colours come from a dedicated art palette in tokens.css. Deliberately NOT
 * the chart hues (those mean series) and never the brand gold (that means the
 * intelligence layer is speaking).
 */
final class PropertyArt
{
    /** Broad shapes. Several sale types share a silhouette. */
    public const SCENES = [
        'Land' => 'land',
        'Office Space' => 'commercial',
        'Apartment' => 'block',
        'Penthouse' => 'penthouse',
        'Shortlet' => 'shortlet',
        'Bungalow' => 'bungalow',
        'Detached Duplex' => 'detached',
        'Semi-Detached Duplex' => 'semi',
        'Terraced Duplex' => 'terrace',
    ];

    private int $seed;

    public function __construct(private ?Property $property)
    {
        // Deterministic per unit: the same flat must not redraw itself
        // differently on every page load.
        $this->seed = crc32(($property?->ref ?? 'NX').':'.($property?->id ?? 0));
    }

    public static function for(?Property $property): self
    {
        return new self($property);
    }

    /** A small linear congruential generator — repeatable, and enough. */
    private function next(): float
    {
        $this->seed = ($this->seed * 1103515245 + 12345) & 0x7FFFFFFF;

        return $this->seed / 0x7FFFFFFF;
    }

    private function int(int $min, int $max): int
    {
        return $min + (int) floor($this->next() * ($max - $min + 1));
    }

    private function chance(float $p): bool
    {
        return $this->next() < $p;
    }

    public function scene(): string
    {
        return self::SCENES[$this->property?->type] ?? 'block';
    }

    public function isLand(): bool
    {
        return $this->scene() === 'land';
    }

    public function isCommercial(): bool
    {
        return in_array($this->scene(), ['commercial', 'block', 'penthouse'], true);
    }

    /**
     * The whole drawing, as SVG markup for a 400×250 viewBox.
     *
     * Returned as a string rather than assembled in Blade because this is
     * geometry, not markup — and a template full of trigonometry is a template
     * nobody will touch again.
     */
    public function svg(): string
    {
        $scene = $this->scene();

        // Sky band varies a little per unit so a grid of cards is not nine
        // copies of one picture.
        $dawn = $this->chance(0.45);
        $skyId = 'sky'.abs(crc32($scene.$this->property?->ref));

        $parts = [
            $this->sky($skyId, $dawn),
            match ($scene) {
                'land' => $this->land(),
                'commercial' => $this->commercial(),
                'block' => $this->block(),
                'penthouse' => $this->penthouse(),
                'shortlet' => $this->shortlet(),
                'bungalow' => $this->bungalow(),
                'detached' => $this->detached(),
                'semi' => $this->semi(),
                'terrace' => $this->terrace(),
                default => $this->block(),
            },
        ];

        return implode("\n", $parts);
    }

    /* ------------------------------------------------------------- pieces */

    private function sky(string $id, bool $dawn): string
    {
        $top = $dawn ? 'var(--art-sky-1)' : 'var(--art-sky-3)';
        $bottom = $dawn ? 'var(--art-sky-2)' : 'var(--art-sky-4)';

        $svg = '<defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0" stop-color="'.$top.'"/>'
            .'<stop offset="1" stop-color="'.$bottom.'"/>'
            .'</linearGradient></defs>'
            .'<rect width="400" height="250" fill="url(#'.$id.')"/>';

        // A sun or moon, low and small. Placed away from wherever the building
        // will sit so it is never a halo behind a roofline.
        $cx = $this->chance(0.5) ? $this->int(40, 90) : $this->int(310, 360);
        $svg .= '<circle cx="'.$cx.'" cy="'.$this->int(38, 58).'" r="'.$this->int(11, 16)
            .'" fill="var(--art-sun)" opacity="'.($dawn ? '.55' : '.32').'"/>';

        // Distant skyline, so nothing floats in an empty field.
        $x = -10;
        $far = '';
        while ($x < 410) {
            $w = $this->int(16, 34);
            $h = $this->int(14, 46);
            $far .= '<rect x="'.$x.'" y="'.(178 - $h).'" width="'.$w.'" height="'.$h.'"/>';
            $x += $w + $this->int(2, 9);
        }

        return $svg.'<g fill="var(--art-far)" opacity=".5">'.$far.'</g>'
            .'<rect y="178" width="400" height="72" fill="var(--art-ground)"/>';
    }

    /** A grid of lit and unlit windows on a face. */
    private function windows(int $x, int $y, int $cols, int $rows, int $gap = 14, int $w = 9, int $h = 12): string
    {
        $out = '';

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $lit = $this->chance(0.34);
                $out .= '<rect x="'.($x + $c * $gap).'" y="'.($y + $r * ($h + 6))
                    .'" width="'.$w.'" height="'.$h.'" rx="1" fill="'
                    .($lit ? 'var(--art-lit)' : 'var(--art-glass)').'"/>';
            }
        }

        return $out;
    }

    /** Palms and shrubs — the thing that makes a scene read as Lagos. */
    private function planting(int ...$xs): string
    {
        $out = '';

        foreach ($xs as $x) {
            $h = $this->int(26, 42);
            $out .= '<rect x="'.($x - 1).'" y="'.(190 - $h).'" width="3" height="'.$h.'" fill="var(--art-trunk)"/>';

            for ($i = 0; $i < 5; $i++) {
                $a = -140 + $i * 25 + $this->int(-8, 8);
                $rad = deg2rad($a);
                $out .= '<path d="M'.$x.' '.(190 - $h).' q '
                    .round(cos($rad) * 13).' '.round(sin($rad) * 9).' '
                    .round(cos($rad) * 22).' '.round(sin($rad) * 6)
                    .'" stroke="var(--art-leaf)" stroke-width="3" fill="none" stroke-linecap="round"/>';
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------- scenes */

    /**
     * A surveyed plot.
     *
     * Beacons, a boundary and nothing built. The single most important thing
     * this drawing does is not look like a house — land is a different
     * purchase, and a buyer scanning a grid should never have to read the
     * label to tell them apart.
     */
    private function land(): string
    {
        $svg = '<path d="M40 232 L128 168 L330 168 L390 232 Z" fill="var(--art-plot)" opacity=".85"/>'
            .'<path d="M40 232 L128 168 L330 168 L390 232 Z" fill="none" '
            .'stroke="var(--art-line)" stroke-width="2" stroke-dasharray="7 5"/>';

        // Survey beacons at the corners.
        foreach ([[40, 232], [128, 168], [330, 168], [390, 232]] as [$bx, $by]) {
            $svg .= '<rect x="'.($bx - 4).'" y="'.($by - 10).'" width="8" height="12" rx="1" fill="var(--art-beacon)"/>';
        }

        // Tufts of grass across the plot.
        for ($i = 0; $i < 22; $i++) {
            $gx = $this->int(60, 370);
            $gy = $this->int(176, 226);
            $svg .= '<path d="M'.$gx.' '.$gy.' l-3 -6 M'.$gx.' '.$gy.' l0 -8 M'.$gx.' '.$gy.' l3 -6" '
                .'stroke="var(--art-leaf)" stroke-width="1.5" opacity=".55" stroke-linecap="round"/>';
        }

        // A corner post with a board on it — the universal sign of a plot.
        $svg .= '<rect x="300" y="120" width="4" height="60" fill="var(--art-trunk)"/>'
            .'<rect x="272" y="112" width="60" height="26" rx="2" fill="var(--art-face)" '
            .'stroke="var(--art-line)" stroke-width="1.5"/>'
            .'<rect x="278" y="120" width="34" height="3" rx="1.5" fill="var(--art-line)" opacity=".6"/>'
            .'<rect x="278" y="127" width="22" height="3" rx="1.5" fill="var(--art-line)" opacity=".4"/>';

        return $svg.$this->planting(72, 355);
    }

    /** A glass commercial tower. */
    private function commercial(): string
    {
        $floors = $this->int(6, 9);
        $h = $floors * 18 + 14;
        $x = 132;
        $w = 136;
        $y = 178 - $h;

        $svg = '<rect x="'.($x - 16).'" y="'.($y + 10).'" width="16" height="'.($h - 10).'" fill="var(--art-side)"/>'
            .'<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="var(--art-face)"/>';

        // Continuous glazing bands rather than punched windows — that band is
        // what makes a building read as commercial rather than residential.
        for ($f = 0; $f < $floors; $f++) {
            $fy = $y + 12 + $f * 18;
            $svg .= '<rect x="'.($x + 8).'" y="'.$fy.'" width="'.($w - 16).'" height="11" rx="1" fill="var(--art-glass)"/>';

            for ($c = 0; $c < 6; $c++) {
                if ($this->chance(0.3)) {
                    $svg .= '<rect x="'.($x + 10 + $c * 20).'" y="'.$fy.'" width="17" height="11" fill="var(--art-lit)"/>';
                }
            }
        }

        // Crown and a ground-floor entrance.
        $svg .= '<rect x="'.($x + 46).'" y="'.($y - 16).'" width="4" height="16" fill="var(--art-line)"/>'
            .'<rect x="'.($x + 40).'" y="150" width="56" height="28" fill="var(--art-glass)"/>'
            .'<rect x="'.($x + 40).'" y="150" width="56" height="28" fill="none" stroke="var(--art-line)" stroke-width="1.5"/>'
            .'<rect x="'.($x + 20).'" y="176" width="96" height="4" rx="2" fill="var(--art-line)" opacity=".5"/>';

        return $svg.$this->planting(96, 300, 330);
    }

    /** A mid-rise residential block with balconies. */
    private function block(): string
    {
        $floors = $this->int(4, 6);
        $h = $floors * 26 + 10;
        $x = 120;
        $w = 160;
        $y = 178 - $h;

        $svg = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="'.$h.'" fill="var(--art-face)"/>'
            .'<rect x="'.($x + $w).'" y="'.($y + 12).'" width="18" height="'.($h - 12).'" fill="var(--art-side)"/>'
            .'<rect x="'.($x - 4).'" y="'.($y - 8).'" width="'.($w + 8).'" height="10" rx="2" fill="var(--art-roof)"/>';

        for ($f = 0; $f < $floors; $f++) {
            $fy = $y + 14 + $f * 26;
            $svg .= $this->windows($x + 14, $fy, 4, 1, 24, 14, 13);

            // A balcony rail on alternating floors.
            if ($f % 2 === 0) {
                $svg .= '<rect x="'.($x + 10).'" y="'.($fy + 15).'" width="'.($w - 20).'" height="3" rx="1.5" '
                    .'fill="var(--art-line)" opacity=".55"/>';
            }
        }

        $svg .= '<rect x="'.($x + 62).'" y="146" width="34" height="32" rx="2" fill="var(--art-door)"/>'
            .'<rect x="'.($x + 40).'" y="176" width="80" height="4" rx="2" fill="var(--art-line)" opacity=".45"/>';

        return $svg.$this->planting(86, 314);
    }

    /** The top of a tower, with a roof terrace. */
    private function penthouse(): string
    {
        $x = 118;
        $w = 164;
        $y = 92;

        $svg = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="86" fill="var(--art-face)"/>'
            .'<rect x="'.($x + $w).'" y="'.($y + 10).'" width="16" height="76" fill="var(--art-side)"/>';

        // Full-height glazing — the thing a penthouse is actually sold on.
        $svg .= '<rect x="'.($x + 12).'" y="'.($y + 14).'" width="'.($w - 24).'" height="42" rx="2" fill="var(--art-glass)"/>';
        for ($c = 0; $c < 5; $c++) {
            $svg .= '<rect x="'.($x + 14 + $c * 28).'" y="'.($y + 16).'" width="24" height="38" fill="'
                .($this->chance(0.55) ? 'var(--art-lit)' : 'var(--art-glass)').'"/>';
        }

        // Roof terrace: rail, planters, a parasol.
        $svg .= '<rect x="'.($x - 10).'" y="'.($y - 10).'" width="'.($w + 20).'" height="10" rx="2" fill="var(--art-roof)"/>';
        for ($c = 0; $c < 9; $c++) {
            $svg .= '<rect x="'.($x - 4 + $c * 20).'" y="'.($y - 26).'" width="2" height="16" fill="var(--art-line)" opacity=".6"/>';
        }
        $svg .= '<rect x="'.($x - 6).'" y="'.($y - 28).'" width="'.($w + 12).'" height="3" rx="1.5" fill="var(--art-line)" opacity=".7"/>'
            .'<circle cx="'.($x + 34).'" cy="'.($y - 34).'" r="9" fill="var(--art-leaf)"/>'
            .'<circle cx="'.($x + 122).'" cy="'.($y - 32).'" r="7" fill="var(--art-leaf)"/>';

        // The shaft it sits on.
        $svg .= '<rect x="'.($x + 20).'" y="178" width="'.($w - 40).'" height="0" fill="var(--art-face)"/>';
        for ($f = 0; $f < 3; $f++) {
            $svg .= '<rect x="'.($x + 12).'" y="'.(146 + $f * 0).'" width="'.($w - 24).'" height="1" fill="none"/>';
        }
        $svg .= '<rect x="'.($x + 12).'" y="146" width="'.($w - 24).'" height="32" fill="var(--art-glass)" opacity=".8"/>'
            .'<rect x="'.($x + 66).'" y="152" width="32" height="26" rx="2" fill="var(--art-door)"/>';

        return $svg.$this->planting(84, 330);
    }

    /** A compact furnished unit. */
    private function shortlet(): string
    {
        $x = 148;
        $w = 108;
        $y = 108;

        $svg = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="70" fill="var(--art-face)"/>'
            .'<path d="M'.($x - 12).' '.$y.' L'.($x + $w / 2).' '.($y - 30).' L'.($x + $w + 12).' '.$y.' Z" fill="var(--art-roof)"/>'
            .$this->windows($x + 16, $y + 14, 3, 2, 30, 18, 15)
            .'<rect x="'.($x + 42).'" y="148" width="26" height="30" rx="2" fill="var(--art-door)"/>';

        // An awning, because a shortlet is sold on looking like a stay.
        $svg .= '<path d="M'.($x + 34).' 146 l42 0 l6 -10 l-54 0 Z" fill="var(--art-awning)"/>'
            .'<rect x="'.($x - 6).'" y="176" width="'.($w + 12).'" height="4" rx="2" fill="var(--art-line)" opacity=".45"/>';

        return $svg.$this->planting(112, 292, 318);
    }

    /** Single storey, pitched roof, veranda. */
    private function bungalow(): string
    {
        $x = 108;
        $w = 184;
        $y = 116;

        $svg = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="62" fill="var(--art-face)"/>'
            .'<path d="M'.($x - 16).' '.$y.' L'.($x + $w / 2).' '.($y - 34).' L'.($x + $w + 16).' '.$y.' Z" fill="var(--art-roof)"/>'
            .$this->windows($x + 18, $y + 16, 2, 1, 34, 24, 18)
            .$this->windows($x + 118, $y + 16, 2, 1, 34, 24, 18);

        // Veranda posts.
        $svg .= '<rect x="'.($x + 74).'" y="142" width="32" height="36" rx="2" fill="var(--art-door)"/>';
        foreach ([$x + 62, $x + 118] as $px) {
            $svg .= '<rect x="'.$px.'" y="132" width="4" height="46" fill="var(--art-line)" opacity=".6"/>';
        }
        $svg .= '<rect x="'.($x + 56).'" y="128" width="72" height="5" rx="2" fill="var(--art-roof)"/>'
            .'<rect x="'.($x - 10).'" y="176" width="'.($w + 20).'" height="4" rx="2" fill="var(--art-line)" opacity=".45"/>';

        return $svg.$this->planting(76, 320, 348);
    }

    /** Two storeys, standalone, with a gate. */
    private function detached(): string
    {
        $x = 118;
        $w = 168;
        $y = 82;

        $svg = '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="96" fill="var(--art-face)"/>'
            .'<rect x="'.($x + $w).'" y="'.($y + 14).'" width="16" height="82" fill="var(--art-side)"/>'
            .'<path d="M'.($x - 14).' '.$y.' L'.($x + $w / 2).' '.($y - 30).' L'.($x + $w + 26).' '.$y.' Z" fill="var(--art-roof)"/>'
            .$this->windows($x + 18, $y + 18, 3, 1, 44, 28, 20)
            .$this->windows($x + 18, $y + 58, 2, 1, 44, 28, 20);

        // Porch and door.
        $svg .= '<rect x="'.($x + 108).'" y="132" width="34" height="46" rx="2" fill="var(--art-door)"/>'
            .'<rect x="'.($x + 100).'" y="126" width="50" height="6" rx="2" fill="var(--art-roof)"/>';

        // Compound wall and gate — a detached duplex in Lagos has one.
        $svg .= '<rect x="40" y="152" width="320" height="4" fill="var(--art-line)" opacity=".35"/>'
            .'<rect x="46" y="156" width="18" height="24" fill="var(--art-side)" opacity=".7"/>'
            .'<rect x="336" y="156" width="18" height="24" fill="var(--art-side)" opacity=".7"/>';

        return $svg.$this->planting(74, 330);
    }

    /** Two units sharing a party wall. */
    private function semi(): string
    {
        $y = 96;
        $svg = '';

        foreach ([[92, 108], [200, 108]] as $i => [$x, $w]) {
            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="82" fill="var(--art-face)"/>'
                .'<path d="M'.($x - 8).' '.$y.' L'.($x + $w / 2).' '.($y - 26).' L'.($x + $w + 8).' '.$y.' Z" fill="var(--art-roof)"/>'
                .$this->windows($x + 14, $y + 16, 2, 1, 40, 24, 17)
                .'<rect x="'.($x + 38).'" y="142" width="28" height="36" rx="2" fill="var(--art-door)"/>';
        }

        // The party wall, drawn so the pair reads as joined rather than as two
        // houses that happen to be next to each other.
        $svg .= '<rect x="198" y="'.($y - 26).'" width="4" height="104" fill="var(--art-line)" opacity=".45"/>'
            .'<rect x="80" y="176" width="240" height="4" rx="2" fill="var(--art-line)" opacity=".45"/>';

        return $svg.$this->planting(60, 344);
    }

    /** A row of three. */
    private function terrace(): string
    {
        $y = 102;
        $svg = '';
        $x = 74;

        for ($i = 0; $i < 3; $i++) {
            $w = 84;
            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$w.'" height="76" fill="var(--art-face)"/>'
                .'<path d="M'.($x - 4).' '.$y.' L'.($x + $w / 2).' '.($y - 22).' L'.($x + $w + 4).' '.$y.' Z" fill="var(--art-roof)"/>'
                .$this->windows($x + 12, $y + 14, 2, 1, 34, 22, 16)
                .'<rect x="'.($x + 30).'" y="146" width="24" height="32" rx="2" fill="var(--art-door)"/>';

            if ($i < 2) {
                $svg .= '<rect x="'.($x + $w).'" y="'.($y - 22).'" width="3" height="98" fill="var(--art-line)" opacity=".4"/>';
            }

            $x += $w + 3;
        }

        $svg .= '<rect x="66" y="176" width="272" height="4" rx="2" fill="var(--art-line)" opacity=".45"/>';

        return $svg.$this->planting(52, 352);
    }
}
