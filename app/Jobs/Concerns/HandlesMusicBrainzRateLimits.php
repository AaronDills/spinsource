<?php

namespace App\Jobs\Concerns;

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
        $ua = config('musicbrainz.user_agent');

        // Always request JSON format
        $query['fmt'] = 'json';

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'User-Agent' => $ua,
        ])
            ->connectTimeout(10)
            ->timeout(30)
            ->get("{$endpoint}/{$path}", $query);

        // Handle rate-limited responses
        if ($this->handleRateLimitedResponse($response, ['path' => $path])) {
            return null;
        }

        // Throw for other error status codes
        $response->throw();

        return $response;
    }
}
