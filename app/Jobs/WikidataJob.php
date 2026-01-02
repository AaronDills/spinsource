<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesWikidataRateLimits;
use App\Jobs\Concerns\TracksJobMetrics;
use Carbon\Carbon;

/**
 * Abstract base class for all Wikidata jobs.
 *
 * Extends RateLimitedApiJob for common queue/retry configuration.
 * Adds Wikidata-specific functionality:
 * - SPARQL request execution via HandlesWikidataRateLimits trait
 * - Job run metrics tracking via TracksJobMetrics trait
 * - Helper methods for parsing Wikidata responses (QIDs, dates)
 *
 * Child classes should call parent::__construct() in their constructor.
 */
abstract class WikidataJob extends RateLimitedApiJob
{
    use HandlesWikidataRateLimits;
    use TracksJobMetrics;
    use \App\Jobs\Concerns\RecordsJobHeartbeat;

    /**
     * Get the queue name for Wikidata jobs.
     */
    protected function queueName(): string
    {
        return 'wikidata';
    }

    /**
     * Get the rate limiter name for Wikidata jobs.
     */
    protected function rateLimiterName(): string
    {
        return 'wikidata-wdqs';
    }

    /**
     * Extract Q-ID from Wikidata entity URL.
     *
     * @param  string|null  $url  e.g., "http://www.wikidata.org/entity/Q123"
     * @return string|null e.g., "Q123"
     */
    protected function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $pos = strrpos($url, '/');
        if ($pos === false) {
            return null;
        }

        $qid = substr($url, $pos + 1);

        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }

    /**
     * Extract year from Wikidata date value.
     *
     * Handles various date formats including:
     * - Full ISO dates: "+2023-01-15T00:00:00Z"
     * - Year-only: "+2023"
     * - Malformed dates
     */
    protected function extractYear(?string $dateValue): ?int
    {
        if (! $dateValue) {
            return null;
        }

        // Strip leading + from Wikidata dates
        $clean = ltrim($dateValue, '+');

        try {
            return Carbon::parse($clean)->year;
        } catch (\Throwable) {
            // Fallback: extract 4-digit year from string
            if (preg_match('/(\d{4})/', $clean, $matches)) {
                return (int) $matches[1];
            }

            return null;
        }
    }

    /**
     * Parse a date value into a Carbon instance.
     */
    protected function parseDate(?string $dateValue): ?Carbon
    {
        if (! $dateValue) {
            return null;
        }

        $clean = ltrim($dateValue, '+');

        try {
            return Carbon::parse($clean);
        } catch (\Throwable) {
            return null;
        }
    }
}
