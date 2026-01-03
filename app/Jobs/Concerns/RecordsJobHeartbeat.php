<?php

namespace App\Jobs\Concerns;

use App\Models\JobHeartbeat;
use Illuminate\Support\Str;

trait RecordsJobHeartbeat
{
    /**
     * Unique identifier for this job run.
     */
    protected ?string $heartbeatRunId = null;

    /**
     * Get or generate the run ID for this job execution.
     */
    protected function getHeartbeatRunId(): string
    {
        if ($this->heartbeatRunId === null) {
            $this->heartbeatRunId = Str::uuid()->toString();
        }

        return $this->heartbeatRunId;
    }

    /**
     * Record a heartbeat with run tracking.
     */
    protected function recordHeartbeat(string $metric, array $context = []): void
    {
        try {
            JobHeartbeat::create([
                'job' => class_basename(static::class),
                'run_id' => $this->getHeartbeatRunId(),
                'metric' => $metric,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            // Swallow any errors — monitoring must not break jobs
        }
    }

    /**
     * Record job start. Call at the beginning of handle().
     */
    protected function heartbeatStarted(array $context = []): void
    {
        $this->recordHeartbeat('started', $context);
    }

    /**
     * Record job completion. Call at the end of successful handle().
     */
    protected function heartbeatCompleted(array $context = []): void
    {
        $this->recordHeartbeat('completed', $context);
    }

    /**
     * Record job failure.
     */
    protected function heartbeatFailed(string $error, array $context = []): void
    {
        $this->recordHeartbeat('failed', array_merge($context, [
            'error' => Str::limit($error, 500),
        ]));
    }

    /**
     * Record progress update for long-running jobs.
     */
    protected function heartbeatProgress(int $current, int $total, array $context = []): void
    {
        $percent = $total > 0 ? round(($current / $total) * 100, 1) : 0;

        $this->recordHeartbeat('progress', array_merge($context, [
            'current' => $current,
            'total' => $total,
            'percent' => $percent,
        ]));
    }

    /**
     * Wrap a job's handle logic with automatic heartbeat tracking.
     *
     * Usage in your job:
     *   public function handle() {
     *       $this->withHeartbeat(function() {
     *           // your job logic here
     *       });
     *   }
     */
    protected function withHeartbeat(callable $callback, array $startContext = []): mixed
    {
        $this->heartbeatStarted($startContext);

        try {
            $result = $callback();
            $this->heartbeatCompleted();
            return $result;
        } catch (\Throwable $e) {
            $this->heartbeatFailed($e->getMessage());
            throw $e;
        }
    }
}
