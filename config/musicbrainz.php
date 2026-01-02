<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MusicBrainz API Endpoint
    |--------------------------------------------------------------------------
    |
    | The base URL for the MusicBrainz Web Service API.
    |
    */

    'endpoint' => env('MUSICBRAINZ_API_ENDPOINT', 'https://musicbrainz.org/ws/2/'),

    /*
    |--------------------------------------------------------------------------
    | User Agent
    |--------------------------------------------------------------------------
    |
    | MusicBrainz REQUIRES a descriptive User-Agent header including
    | application name, version, and contact information. Requests
    | without a proper User-Agent may be blocked.
    |
    | Format: AppName/Version (contact@example.com)
    |
    */

    'user_agent' => env('MUSICBRAINZ_USER_AGENT', 'SpinSource/1.0 (contact@example.com)'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | MusicBrainz allows approximately 1 request per second (50-60 per minute).
    | We use 50 to be conservative and leave headroom for other clients.
    |
    */

    'requests_per_minute' => env('MUSICBRAINZ_REQUESTS_PER_MINUTE', 50),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Fallback delay (in seconds) when Retry-After header is missing,
    | and maximum cap for any retry delay.
    |
    */

    'retry_after_fallback' => env('MUSICBRAINZ_RETRY_AFTER_FALLBACK', 60),

    'retry_after_cap' => env('MUSICBRAINZ_RETRY_AFTER_CAP', 300),

];
