<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

/**
 * Abstract base class for jobs that interact with rate-limited external APIs.
 *
 * Provides common configuration:
 * - High tries count to accommodate rate-limit releases
 * - Low maxExceptions for real failures
 * - Exponential backoff schedule
 * - Automatic queue and rate limiter configuration via abstract methods
 *
 * Child classes must implement:
 * - queueName(): The queue this job runs on
 * - rateLimiterName(): The rate limiter key from AppServiceProvider
 */
abstract class RateLimitedApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts including rate-limit releases.
     * Set high to accommodate many 429/5xx releases.
     */
    public int $tries = 50;

    /**
     * Maximum number of actual exceptions before failing.
     * This is the "real" failure limit - rate-limit releases don't count.
     */
    public int $maxExceptions = 3;

    /**
     * Job timeout in seconds.
     * Override in child classes if needed.
     */
    public int $timeout = 120;

    /**
     * Backoff schedule (seconds) between retry attempts.
     * Exponential backoff to handle transient API issues.
     */
    public array $backoff = [5, 15, 45, 120, 300];

    /**
     * Base constructor - sets the queue from queueName().
     * Child classes should call parent::__construct().
     */
    public function __construct()
    {
        $this->onQueue($this->queueName());
    }

    /**
     * Get the queue name for this job type.
     */
    abstract protected function queueName(): string;

    /**
     * Get the rate limiter name for this job type.
     */
    abstract protected function rateLimiterName(): string;

    /**
     * Middleware applied to all rate-limited API jobs.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited($this->rateLimiterName())];
    }

    protected function logStart(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => static::class,
            'phase' => 'start',
        ], $context));
    }

    protected function logEnd(string $message, array $context = []): void
    {
        Log::info($message, array_merge([
            'job' => static::class,
            'phase' => 'end',
        ], $context));
    }
}
