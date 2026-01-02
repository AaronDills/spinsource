<?php

namespace App\Jobs\Concerns;

use App\Models\JobRun;
use Illuminate\Support\Facades\Log;

/**
 * Trait for tracking job run metrics and implementing incremental/delta updates.
 *
 * ## Usage
 *
 * 1. Use this trait in your job class
 * 2. Override `jobRunName()` to return a unique identifier for this job type
 * 3. Call `$this->startJobRun()` at the beginning of handle()
 * 4. Use increment methods during processing
 * 5. Call `$this->finishJobRun()` or `$this->failJobRun()` at the end
 *
 * ## Soft Delta / Rotating Window Mode
 *
 * For jobs where true incremental updates aren't possible (e.g., no "modified since"
 * filter available), use the cursor-based pagination:
 *
 * ```php
 * // In handle():
 * $cursor = $this->getLastCursor() ?? '0';
 * $this->startJobRun($cursor);
 *
 * // Process $this->getPageSize() entities starting from $cursor
 * // ...
 *
 * // At the end:
 * $this->finishJobRun($newCursor);
 * ```
 *
 * ## Tuning Parameters
 *
 * - Page size (N): Number of entities to process per run. Trade-off between
 *   job duration and how quickly you cycle through all entities.
 *   - Small N (50-100): Shorter jobs, gentler on API limits, but slower full cycle
 *   - Large N (500-1000): Faster full cycle, but longer job runs
 *
 * - For weekly jobs: N = total_entities / 7 processes everything in ~1 week
 * - For daily jobs: N = total_entities ensures a complete cycle daily
 *
 * The cursor wraps around when it reaches the end, so the job continuously
 * rotates through all entities.
 */
trait TracksJobMetrics
{
    /**
     * The current job run instance.
     */
    protected ?JobRun $jobRun = null;

    /**
     * Default page size for soft-delta processing.
     * Override in child classes as needed.
     */
    protected int $defaultPageSize = 500;

    /**
     * Get the unique name for this job type.
     * Override in child classes.
     */
    protected function jobRunName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get the page size for soft-delta processing.
     * Can be overridden per-job or passed as constructor parameter.
     */
    protected function getPageSize(): int
    {
        return $this->defaultPageSize;
    }

    /**
     * Start a new job run.
     *
     * @param  string|null  $cursor  Initial cursor position (for soft-delta jobs)
     */
    protected function startJobRun(?string $cursor = null): JobRun
    {
        $this->jobRun = JobRun::startRun($this->jobRunName(), $cursor);

        Log::info("{$this->jobRunName()}: Started", [
            'run_id' => $this->jobRun->id,
            'cursor' => $cursor,
        ]);

        return $this->jobRun;
    }

    /**
     * Get the current job run.
     */
    protected function getJobRun(): ?JobRun
    {
        return $this->jobRun;
    }

    /**
     * Increment a counter (processed, created, updated, skipped, errors, api_calls).
     */
    protected function incrementMetric(string $key, int $amount = 1): void
    {
        $this->jobRun?->increment($key, $amount);
    }

    /**
     * Convenience: increment processed count.
     */
    protected function incrementProcessed(int $amount = 1): void
    {
        $this->incrementMetric('processed', $amount);
    }

    /**
     * Convenience: increment created count.
     */
    protected function incrementCreated(int $amount = 1): void
    {
        $this->incrementMetric('created', $amount);
    }

    /**
     * Convenience: increment updated count.
     */
    protected function incrementUpdated(int $amount = 1): void
    {
        $this->incrementMetric('updated', $amount);
    }

    /**
     * Convenience: increment skipped count.
     */
    protected function incrementSkipped(int $amount = 1): void
    {
        $this->incrementMetric('skipped', $amount);
    }

    /**
     * Convenience: increment errors count.
     */
    protected function incrementErrors(int $amount = 1): void
    {
        $this->incrementMetric('errors', $amount);
    }

    /**
     * Convenience: increment API calls count.
     */
    protected function incrementApiCalls(int $amount = 1): void
    {
        $this->incrementMetric('api_calls', $amount);
    }

    /**
     * Finish the job run with success.
     *
     * @param  string|null  $cursor  Final cursor position for next run
     */
    protected function finishJobRun(?string $cursor = null): void
    {
        if (! $this->jobRun) {
            return;
        }

        $this->jobRun->success($cursor);

        Log::info("{$this->jobRunName()}: Completed", [
            'run_id' => $this->jobRun->id,
            'duration_seconds' => $this->jobRun->started_at->diffInSeconds($this->jobRun->finished_at),
            'cursor' => $cursor,
            'summary' => $this->jobRun->summary(),
        ]);
    }

    /**
     * Mark the job run as failed.
     */
    protected function failJobRun(?string $errorMessage = null): void
    {
        if (! $this->jobRun) {
            return;
        }

        $this->jobRun->fail($errorMessage);

        Log::error("{$this->jobRunName()}: Failed", [
            'run_id' => $this->jobRun->id,
            'error' => $errorMessage,
            'summary' => $this->jobRun->summary(),
        ]);
    }

    /**
     * Get the cursor from the last run (any status).
     */
    protected function getLastCursor(): ?string
    {
        return JobRun::lastCursor($this->jobRunName());
    }

    /**
     * Get the timestamp from the last successful run.
     * Useful for "modified since" queries.
     */
    protected function getLastSuccessfulAt(): ?\Carbon\Carbon
    {
        return JobRun::lastSuccessfulAt($this->jobRunName());
    }

    /**
     * Get the last successful job run.
     */
    protected function getLastSuccessfulRun(): ?JobRun
    {
        return JobRun::lastSuccessful($this->jobRunName());
    }

    /**
     * Check if this job is already running.
     * Useful for preventing overlapping runs.
     */
    protected function isJobRunning(): bool
    {
        return JobRun::isRunning($this->jobRunName());
    }

    /**
     * Set batch totals at once (for bulk operations).
     */
    protected function setMetrics(array $values): void
    {
        $this->jobRun?->setTotals($values);
    }

    /**
     * Update cursor during processing (for checkpoint saves).
     */
    protected function updateCursor(string $cursor): void
    {
        $this->jobRun?->setCursor($cursor);
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
