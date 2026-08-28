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

    /*
    |---------------------------------------------------------------------------
    | Where uploaded files go
    |---------------------------------------------------------------------------
    |
    | Property photographs and promotional artwork. Null means "write straight
    | into public/", which is right on a single machine: the file is served the
    | moment it lands, with no `storage:link` step to forget.
    |
    | It is WRONG on any host with an ephemeral filesystem — Laravel Cloud, Fly,
    | Heroku, a container that gets replaced on deploy. There, everything an
    | operator uploaded disappears at the next release, silently, and the only
    | symptom is broken images on listings somebody added last week.
    |
    | So set NEXABI_UPLOAD_DISK to a durable disk in production (`s3` covers
    | any S3-compatible object storage, which is what Laravel Cloud provides).
    |
    | The value stored in the DATABASE is identical either way — a relative path
    | like `img/properties/20260828-a1b2c3.jpg`. Only the resolution differs, so
    | the same rows work on both, and moving between them is a config change
    | plus a file copy rather than a data migration.
    |
    */

    'upload_disk' => env('NEXABI_UPLOAD_DISK'),

];
