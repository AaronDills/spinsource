<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesMusicBrainzRateLimits;

/**
 * Abstract base class for all MusicBrainz jobs.
 *
 * Extends RateLimitedApiJob for common queue/retry configuration.
 * Adds MusicBrainz-specific functionality:
 * - REST API request execution via HandlesMusicBrainzRateLimits trait
 *
 * Child classes should call parent::__construct() in their constructor.
 */
abstract class MusicBrainzJob extends RateLimitedApiJob
{
    use HandlesMusicBrainzRateLimits;

    /**
     * Get the queue name for MusicBrainz jobs.
     */
    protected function queueName(): string
    {
        return 'musicbrainz';
    }

    /**
     * Get the rate limiter name for MusicBrainz jobs.
     */
    protected function rateLimiterName(): string
    {
        return 'musicbrainz-api';
    }
}
