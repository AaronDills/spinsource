<?php

return [
    'endpoint' => env('WIKIDATA_SPARQL_ENDPOINT', 'https://query.wikidata.org/sparql'),
    // Use a descriptive UA per Wikidata Query Service etiquette
    'user_agent' => env('WIKIDATA_USER_AGENT', 'SpinSource/1.0 (contact@example.com)'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting / Backpressure
    |--------------------------------------------------------------------------
    |
    | These settings control how the application handles WDQS rate limits (429).
    |
    */

    // Max requests per minute across all Wikidata jobs
    'requests_per_minute' => env('WIKIDATA_REQUESTS_PER_MINUTE', 30),

    // Fallback delay (seconds) when Retry-After header is missing
    'retry_after_fallback' => env('WIKIDATA_RETRY_AFTER_FALLBACK', 60),

    // Maximum delay cap (seconds) to prevent excessively long waits
    'retry_after_cap' => env('WIKIDATA_RETRY_AFTER_CAP', 900),
];
