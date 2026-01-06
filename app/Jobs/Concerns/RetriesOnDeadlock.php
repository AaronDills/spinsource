<?php

namespace App\Jobs\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

/**
 * Provides deadlock-aware transaction handling for jobs.
 *
 * MySQL deadlocks (SQLSTATE[40001]) are transient failures that should be retried.
 * This trait wraps DB::transaction with automatic retry logic for deadlocks.
 *
 * Usage:
 *   $this->runWithDeadlockRetry(function () {
 *       // Your transactional code here
 *   });
 */
trait RetriesOnDeadlock
{
    /**
     * Run a callback within a transaction, retrying on deadlock.
     *
     * @param  callable  $callback  The transactional work
     * @param  int  $maxAttempts  Maximum retry attempts (default: 3)
     * @param  int  $baseDelayMs  Base delay between retries in milliseconds (default: 100)
     * @return mixed The callback's return value
     *
     * @throws \Throwable If all attempts fail or a non-deadlock error occurs
     */
    protected function runWithDeadlockRetry(callable $callback, int $maxAttempts = 3, int $baseDelayMs = 100): mixed
    {
        $attempt = 0;
        $lastException = null;

        while (++$attempt <= $maxAttempts) {
            try {
                return DB::transaction($callback);
            } catch (PDOException $e) {
                if (! $this->isDeadlockException($e)) {
                    throw $e;
                }

                $lastException = $e;

                Log::warning('Deadlock detected, retrying transaction', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'job' => static::class,
                    'sqlstate' => $e->getCode(),
                ]);

                if ($attempt < $maxAttempts) {
                    // Exponential backoff with jitter
                    $delay = $baseDelayMs * (2 ** ($attempt - 1));
                    $jitter = random_int(0, (int) ($delay * 0.3));
                    usleep(($delay + $jitter) * 1000);
                }
            }
        }

        Log::error('Deadlock retry exhausted', [
            'attempts' => $maxAttempts,
            'job' => static::class,
        ]);

        throw $lastException;
    }

    /**
     * Check if the exception is a deadlock (SQLSTATE 40001).
     */
    protected function isDeadlockException(PDOException $e): bool
    {
        // SQLSTATE 40001 = Serialization failure (deadlock)
        // MySQL error 1213 = Deadlock found when trying to get lock
        $sqlstate = $e->getCode();
        $message = strtolower($e->getMessage());

        return $sqlstate === '40001'
            || $sqlstate === 40001
            || str_contains($message, 'deadlock')
            || str_contains($message, '1213');
    }
}
