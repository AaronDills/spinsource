<?php

namespace App\Jobs\Concerns;

use GuzzleHttp\Exception\ConnectException as GuzzleConnectException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * MusicBrainz REST API execution with rate-limit handling.
 *
 * Uses the shared HandlesApiRateLimits trait for common retry logic.
 */
trait HandlesMusicBrainzRateLimits
{
    use HandlesApiRateLimits;

    /**
     * Get the config prefix for MusicBrainz.
     */
    protected function configPrefix(): string
    {
        return 'musicbrainz';
    }

    /**
     * Get the service name for logging.
     */
    protected function serviceName(): string
    {
        return 'MusicBrainz';
    }

    /**
     * Execute a MusicBrainz API request with proper rate-limit handling.
     *
     * Returns null if rate-limited (job has been released with delay).
     * Returns the Response on success.
     * Throws on other HTTP failures.
     *
     * @param  string  $path  API path (e.g., 'release-group/UUID')
     * @param  array  $query  Query parameters
     */
    protected function executeMusicBrainzRequest(string $path, array $query = []): ?Response
    {
        $endpoint = rtrim(config('musicbrainz.endpoint'), '/');
        $ua = $this->buildMusicBrainzUserAgent();

        // Always request JSON format
        $query['fmt'] = 'json';

        $maxAttempts = (int) config('musicbrainz.max_attempts', 5);

        $attempt = 0;
        $lastException = null;

        // Retry loop with exponential backoff + jitter for transient errors.
        while (++$attempt <= $maxAttempts) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => $ua,
                ])
                    ->connectTimeout(10)
                    ->timeout(30)
                    ->get("{$endpoint}/{$path}", $query);

                $status = $response->status();

                // Success: return the response
                if ($status >= 200 && $status < 300) {
                    return $response;
                }

                // Retry on 429 (rate limit) or 5xx (server errors)
                if ($status === 429 || ($status >= 500 && $status < 600)) {
                    // If server provided Retry-After, use computed delay; otherwise use backoff
                    $delay = ($status === 429)
                        ? $this->computeRetryDelay($response)
                        : $this->computeExponentialBackoffWithJitter($attempt);

                    // If this was the last attempt, delegate to existing rate-limit handler
                    if ($attempt >= $maxAttempts) {
                        if ($this->handleRateLimitedResponse($response, ['path' => $path])) {
                            return null;
                        }

                        // Not handled as a rate-limit; throw to let job fail/inspect
                        $response->throw();
                    }

                    sleep($delay);

                    continue;
                }

                // Handle 404 gracefully - entity not found is not an error condition
                // Return the response so callers can check and handle appropriately
                if ($status === 404) {
                    return $response;
                }

                // For other 4xx (except 429), do not retry — throw immediately
                $response->throw();

            } catch (HttpConnectionException|GuzzleConnectException $e) {
                // Connection / TLS errors are retryable
                $lastException = $e;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $delay = $this->computeExponentialBackoffWithJitter($attempt);
                sleep($delay);

                continue;

            } catch (GuzzleRequestException $e) {
                // Inspect request exceptions for timeouts or transient TLS/connection issues
                $lastException = $e;

                if (! $this->isRetryableRequestException($e) || $attempt >= $maxAttempts) {
                    throw $e;
                }

                $delay = $this->computeExponentialBackoffWithJitter($attempt);
                sleep($delay);

                continue;
            }
        }

        // If we exhausted attempts and have an exception, rethrow it
        if ($lastException) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Build a MusicBrainz-compliant User-Agent header.
     */
    protected function buildMusicBrainzUserAgent(): string
    {
        $ua = trim((string) config('musicbrainz.user_agent', ''));

        if (! empty($ua)) {
            return $ua;
        }

        $app = config('app.name', 'SpinSource');
        $version = config('app.version') ?: '1.0';
        $contact = env('MUSICBRAINZ_CONTACT_EMAIL') ?: env('MAIL_FROM_ADDRESS') ?: 'no-contact@example.com';

        return sprintf('%s/%s (%s)', $app, $version, $contact);
    }

    /**
     * Compute an exponential backoff delay with jitter (seconds).
     * Uses 2^(attempt-1) base and returns a random delay in [0, base].
     */
    protected function computeExponentialBackoffWithJitter(int $attempt): int
    {
        $cap = (int) config('musicbrainz.retry_after_cap', 300);

        // Base exponential (in seconds)
        $base = (int) min($cap, (1 << max(0, $attempt - 1)));

        // Return random jitter between 0 and base (inclusive), at least 1s
        return max(1, random_int(0, max(1, $base)));
    }

    /**
     * Determine whether a Guzzle RequestException is retryable (timeouts, TLS, connection issues).
     */
    protected function isRetryableRequestException(GuzzleRequestException $e): bool
    {
        $ctx = $e->getHandlerContext() ?: [];

        // cURL error number 28 is timeout
        $errno = $ctx['errno'] ?? null;
        if ((int) $errno === 28) {
            return true;
        }

        // Look for TLS/SSL or timeout substrings in error message/context
        $error = strtolower($ctx['error'] ?? '').' '.strtolower($e->getMessage());

        if (str_contains($error, 'ssl') || str_contains($error, 'tls') || str_contains($error, 'timeout')) {
            return true;
        }

        return false;
    }
}
