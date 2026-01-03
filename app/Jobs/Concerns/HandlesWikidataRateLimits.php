<?php

namespace App\Jobs\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Wikidata SPARQL query execution with rate-limit handling.
 *
 * Uses the shared HandlesApiRateLimits trait for common retry logic,
 * adding Wikidata-specific handling for 403 (IP blocks).
 */
trait HandlesWikidataRateLimits
{
    use HandlesApiRateLimits {
        HandlesApiRateLimits::rateLimitedStatusHandlers as baseRateLimitedStatusHandlers;
    }

    /**
     * Get the config prefix for Wikidata.
     */
    protected function configPrefix(): string
    {
        return 'wikidata';
    }

    /**
     * Get the service name for logging.
     */
    protected function serviceName(): string
    {
        return 'Wikidata WDQS';
    }

    /**
     * Get status code handlers for Wikidata.
     * Adds 403 handling for IP blocks (longer delay).
     *
     * @return array<int, array{message: string, delay: callable}>
     */
    protected function rateLimitedStatusHandlers(): array
    {
        $handlers = $this->baseRateLimitedStatusHandlers();

        // Wikidata blocks IPs with 403 - wait longer
        $handlers[403] = [
            'message' => 'blocked (403)',
            'delay' => fn () => 300 + $this->addJitter(300, 30),
        ];

        return $handlers;
    }

    /**
     * Execute a WDQS request with proper rate-limit handling.
     *
     * Returns null if rate-limited (job has been released with delay).
     * Returns the Response on success.
     * Throws on other HTTP failures.
     */
    protected function executeWdqsRequest(string $sparql): ?Response
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $response = Http::withHeaders([
            'Accept' => 'application/sparql-results+json',
            'User-Agent' => $ua,
            'Accept-Encoding' => 'gzip',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ])
            ->connectTimeout(10)
            ->timeout(120)
            ->asForm()
            ->post($endpoint, [
                'format' => 'json',
                'query' => $sparql,
            ]);

        // Handle rate-limited responses
        if ($this->handleRateLimitedResponse($response)) {
            return null;
        }

        // Throw for other error status codes
        $response->throw();

        return $response;
    }
}
