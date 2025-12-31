<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesWikidataRateLimits;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Abstract base class for all Wikidata jobs.
 *
 * Consolidates common configuration:
 * - Rate limiting via HandlesWikidataRateLimits trait
 * - Default queue 'wikidata' with dedicated Horizon supervisor
 * - Standard retry/backoff configuration
 * - Common helper methods (qidFromEntityUrl, extractYear)
 *
 * Child classes should call parent::__construct() in their constructor.
 */
abstract class WikidataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HandlesWikidataRateLimits;

    /**
     * Maximum number of attempts including rate-limit releases.
     * Set high to accommodate many 429/403/504 releases.
     */
    public int $tries = 50;

    /**
     * Maximum number of actual exceptions before failing.
     * This is the "real" failure limit - rate-limit releases don't count.
     */
    public int $maxExceptions = 3;

    /**
     * Job timeout in seconds.
     * Override in child classes if needed (e.g., 300 for multi-query jobs).
     */
    public int $timeout = 120;

    /**
     * Backoff schedule (seconds) between retry attempts.
     * Exponential backoff to handle transient WDQS issues.
     */
    public array $backoff = [5, 15, 45, 120, 300];

    /**
     * Base constructor - sets the wikidata queue.
     * Child classes should call parent::__construct().
     */
    public function __construct()
    {
        $this->onQueue('wikidata');
    }

    /**
     * Middleware applied to all Wikidata jobs.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('wikidata-wdqs')];
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
