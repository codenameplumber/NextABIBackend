<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Stock photography
    |---------------------------------------------------------------------------
    |
    | The catalogue prefers, in order: the agency's own photograph from
    | properties.images, then a curated stock photograph matched to the sale
    | type, then a drawing generated from the property's own attributes.
    |
    | Turn this off and the middle step is skipped. An agency that only wants
    | its own photography on its own listings gets the drawing for anything it
    | has not photographed yet — which is honest, and can never show the wrong
    | building.
    |
    */

    'stock_photos' => env('NEXABI_STOCK_PHOTOS', true),

];
