<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Font directory
    |--------------------------------------------------------------------------
    |
    | Directory holding the tc-lib-pdf-font definition files (.json/.z/.ctg.z)
    | consumed by TCPDF. Regenerate the definitions from the bundled .ttf
    | sources with `php artisan pdf:import-fonts`.
    |
    */
    'fonts_path' => resource_path('fonts'),

    /*
    |--------------------------------------------------------------------------
    | Default font family
    |--------------------------------------------------------------------------
    |
    | Family name (matching a <family>.json definition file) used for every
    | generated document. Must contain Arabic glyphs.
    |
    */
    'font' => 'arial',

    /*
    |--------------------------------------------------------------------------
    | Currency words
    |--------------------------------------------------------------------------
    |
    | Units used when spelling monetary amounts in Arabic.
    |
    */
    'currency' => 'جنيه',
    'sub_currency' => 'قرش',
];
