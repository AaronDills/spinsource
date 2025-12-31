<?php

namespace App\Jobs\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait HandlesWikidataRateLimits
{
    /**
     * Execute a WDQS request with proper rate-limit handling.
     *
     * Returns null if a 429 was received (job has been released with delay).
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

        // Handle 429 Too Many Requests without throwing
        if ($response->status() === 429) {
            $delay = $this->computeRetryDelay($response);

            Log::warning('Wikidata WDQS rate limited (429), releasing job', [
                'job' => static::class,
                'delay' => $delay,
            ]);

            $this->release($delay);

            return null;
        }

        // Handle 403 Forbidden (IP blocked) - wait longer before retry
        if ($response->status() === 403) {
            $delay = 300; // 5 minutes - Wikidata blocks typically last a few minutes
            $delay += (int) ($delay * mt_rand(0, 30) / 100);

            Log::warning('Wikidata WDQS blocked (403), releasing job with long delay', [
                'job' => static::class,
                'delay' => $delay,
            ]);

            $this->release($delay);

            return null;
        }

        // Handle 504 Gateway Timeout (WDQS overloaded) - retry with delay
        if ($response->status() === 504) {
            $delay = (int) config('wikidata.retry_after_fallback', 60);
            // Add jitter to prevent thundering herd
            $delay += (int) ($delay * mt_rand(0, 30) / 100);

            Log::warning('Wikidata WDQS timeout (504), releasing job', [
                'job' => static::class,
                'delay' => $delay,
            ]);

            $this->release($delay);

            return null;
        }

        // Throw for other error status codes
        $response->throw();

        return $response;
    }

    /**
     * Compute retry delay from Retry-After header or fallback config.
     * Adds jitter to prevent thundering herd.
     */
    protected function computeRetryDelay(Response $response): int
    {
        $retryAfter = $response->header('Retry-After');
        $fallback = (int) config('wikidata.retry_after_fallback', 60);
        $cap = (int) config('wikidata.retry_after_cap', 900);

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $delay = (int) $retryAfter;
        } else {
            $delay = $fallback;
        }

        // Cap the delay
        $delay = min($delay, $cap);

        // Add jitter (0-20% of delay)
        $jitter = (int) ($delay * mt_rand(0, 20) / 100);

        return $delay + $jitter;
    }
}
