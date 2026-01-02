<?php

namespace App\Jobs\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Shared rate limiting logic for external API jobs.
 *
 * Provides common methods for:
 * - Computing retry delays from Retry-After headers
 * - Handling rate-limited responses (429, 503, 504, etc.)
 *
 * API-specific traits should use this trait and provide their
 * own execute methods with service-specific request logic.
 */
trait HandlesApiRateLimits
{
    /**
     * Get the config prefix for this API (e.g., 'wikidata', 'musicbrainz').
     */
    abstract protected function configPrefix(): string;

    /**
     * Get the service name for logging.
     */
    abstract protected function serviceName(): string;

    /**
     * Compute retry delay from Retry-After header or fallback config.
     * Adds jitter to prevent thundering herd.
     */
    protected function computeRetryDelay(Response $response): int
    {
        $prefix = $this->configPrefix();
        $retryAfter = $response->header('Retry-After');
        $fallback = (int) config("{$prefix}.retry_after_fallback", 60);
        $cap = (int) config("{$prefix}.retry_after_cap", 300);

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $delay = (int) $retryAfter;
        } else {
            $delay = $fallback;
        }

        // Cap the delay
        $delay = min($delay, $cap);

        // Add jitter (0-20% of delay)
        return $delay + $this->addJitter($delay, 20);
    }

    /**
     * Handle a rate-limited or overloaded response.
     * Releases the job with appropriate delay if handled.
     *
     * @param  Response  $response  The HTTP response
     * @param  array  $logContext  Additional context for logging
     * @return bool True if the response was handled (job released), false otherwise
     */
    protected function handleRateLimitedResponse(Response $response, array $logContext = []): bool
    {
        $status = $response->status();
        $handlers = $this->rateLimitedStatusHandlers();

        if (! isset($handlers[$status])) {
            return false;
        }

        $handler = $handlers[$status];
        $delay = $handler['delay']($response);

        Log::warning("{$this->serviceName()} {$handler['message']}, releasing job", array_merge([
            'job' => static::class,
            'delay' => $delay,
        ], $logContext));

        $this->release($delay);

        return true;
    }

    /**
     * Get the status code handlers for this API.
     * Override to customize which status codes are handled.
     *
     * @return array<int, array{message: string, delay: callable}>
     */
    protected function rateLimitedStatusHandlers(): array
    {
        $prefix = $this->configPrefix();

        return [
            429 => [
                'message' => 'rate limited (429)',
                'delay' => fn (Response $r) => $this->computeRetryDelay($r),
            ],
            503 => [
                'message' => 'service unavailable (503)',
                'delay' => fn () => $this->computeFallbackDelay($prefix),
            ],
            504 => [
                'message' => 'timeout (504)',
                'delay' => fn () => $this->computeFallbackDelay($prefix),
            ],
        ];
    }

    /**
     * Compute a fallback delay with jitter.
     */
    protected function computeFallbackDelay(string $configPrefix): int
    {
        $delay = (int) config("{$configPrefix}.retry_after_fallback", 60);

        return $delay + $this->addJitter($delay, 30);
    }

    /**
     * Add jitter to a delay value.
     *
     * @param  int  $delay  Base delay in seconds
     * @param  int  $maxPercent  Maximum jitter percentage (0-100)
     */
    protected function addJitter(int $delay, int $maxPercent): int
    {
        return (int) ($delay * mt_rand(0, $maxPercent) / 100);
    }
}
