<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesMusicBrainzRateLimits;
use App\Jobs\Concerns\TracksJobMetrics;

/**
 * Abstract base class for all MusicBrainz jobs.
 *
 * Extends RateLimitedApiJob for common queue/retry configuration.
 * Adds MusicBrainz-specific functionality:
 * - REST API request execution via HandlesMusicBrainzRateLimits trait
 * - Job run metrics tracking via TracksJobMetrics trait
 *
 * Child classes should call parent::__construct() in their constructor.
 */
abstract class MusicBrainzJob extends RateLimitedApiJob
{
    use HandlesMusicBrainzRateLimits;
    use TracksJobMetrics;
    use \App\Jobs\Concerns\RecordsJobHeartbeat;

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
