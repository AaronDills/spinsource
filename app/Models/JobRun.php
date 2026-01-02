<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class JobRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'job_name',
        'started_at',
        'finished_at',
        'status',
        'totals',
        'last_cursor',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'totals' => 'array',
    ];

    /**
     * Get the last successful run for a job.
     */
    public static function lastSuccessful(string $jobName): ?self
    {
        return self::where('job_name', $jobName)
            ->where('status', self::STATUS_SUCCESS)
            ->orderByDesc('finished_at')
            ->first();
    }

    /**
     * Get the last run (any status) for a job.
     */
    public static function lastRun(string $jobName): ?self
    {
        return self::where('job_name', $jobName)
            ->orderByDesc('started_at')
            ->first();
    }

    /**
     * Get the last cursor from the most recent run.
     */
    public static function lastCursor(string $jobName): ?string
    {
        return self::where('job_name', $jobName)
            ->whereNotNull('last_cursor')
            ->orderByDesc('started_at')
            ->value('last_cursor');
    }

    /**
     * Get the timestamp from the last successful run.
     * Useful for "since" queries.
     */
    public static function lastSuccessfulAt(string $jobName): ?Carbon
    {
        return self::lastSuccessful($jobName)?->finished_at;
    }

    /**
     * Start a new run.
     */
    public static function startRun(string $jobName, ?string $cursor = null): self
    {
        return self::create([
            'job_name' => $jobName,
            'started_at' => now(),
            'status' => self::STATUS_RUNNING,
            'last_cursor' => $cursor,
            'totals' => [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'api_calls' => 0,
            ],
        ]);
    }

    /**
     * Increment a counter in totals.
     */
    public function increment(string $key, int $amount = 1): self
    {
        $totals = $this->totals ?? [];
        $totals[$key] = ($totals[$key] ?? 0) + $amount;
        $this->totals = $totals;
        $this->save();

        return $this;
    }

    /**
     * Get a total value.
     */
    public function getTotal(string $key): int
    {
        return $this->totals[$key] ?? 0;
    }

    /**
     * Set multiple totals at once.
     */
    public function setTotals(array $values): self
    {
        $totals = $this->totals ?? [];
        foreach ($values as $key => $value) {
            $totals[$key] = $value;
        }
        $this->totals = $totals;
        $this->save();

        return $this;
    }

    /**
     * Update the cursor position.
     */
    public function setCursor(?string $cursor): self
    {
        $this->last_cursor = $cursor;
        $this->save();

        return $this;
    }

    /**
     * Finish the run with success status.
     */
    public function success(?string $cursor = null): self
    {
        $this->status = self::STATUS_SUCCESS;
        $this->finished_at = now();
        if ($cursor !== null) {
            $this->last_cursor = $cursor;
        }
        $this->save();

        return $this;
    }

    /**
     * Finish the run with failed status.
     */
    public function fail(?string $errorMessage = null): self
    {
        $this->status = self::STATUS_FAILED;
        $this->finished_at = now();
        if ($errorMessage !== null) {
            $this->error_message = $errorMessage;
        }
        $this->save();

        return $this;
    }

    /**
     * Get a summary string for logging.
     */
    public function summary(): string
    {
        $totals = $this->totals ?? [];
        $parts = [];

        foreach (['processed', 'created', 'updated', 'skipped', 'errors', 'api_calls'] as $key) {
            if (isset($totals[$key]) && $totals[$key] > 0) {
                $parts[] = "{$key}={$totals[$key]}";
            }
        }

        return implode(', ', $parts) ?: 'no changes';
    }

    /**
     * Check if there's a currently running instance of this job.
     */
    public static function isRunning(string $jobName): bool
    {
        return self::where('job_name', $jobName)
            ->where('status', self::STATUS_RUNNING)
            ->exists();
    }

    /**
     * Get runs that have been stuck in "running" status for too long.
     */
    public static function staleRuns(int $minutesThreshold = 60): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('status', self::STATUS_RUNNING)
            ->where('started_at', '<', now()->subMinutes($minutesThreshold))
            ->get();
    }
}
