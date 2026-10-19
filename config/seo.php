<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Autocomplete
    |--------------------------------------------------------------------------
    |
    | The free, unofficial suggestion endpoint behind Google's search box. It
    | feeds content briefs with what people really type. It is rate-limited by
    | Google and can be blocked from some hosts, so every call is cached and a
    | failure just means no suggestions.
    |
    */

    'autocomplete' => [
        'enabled' => env('SEO_AUTOCOMPLETE_ENABLED', true),
        'cache_days' => 7,
    ],

];
