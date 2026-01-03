<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class JobHeartbeat extends Model
{
    protected $table = 'job_heartbeats';
    protected $guarded = [];
    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Record a heartbeat from a job.
     *
     * @param string|object $job The job class name or instance
     * @param string $metric The metric name (e.g., 'started', 'completed', 'progress')
     * @param array $context Optional context data
     * @param string|null $runId Optional run ID to link related heartbeats
     * @return static|null
     */
    public static function record(string|object $job, string $metric = 'heartbeat', array $context = [], ?string $runId = null): ?static
    {
        // Gracefully handle missing table
        if (! Schema::hasTable('job_heartbeats')) {
            return null;
        }

        $jobName = is_object($job) ? get_class($job) : $job;

        // Extract short class name for readability
        $shortName = class_basename($jobName);

        $data = [
            'job' => $shortName,
            'metric' => $metric,
            'context' => $context,
        ];

        // Add run_id if the column exists
        if ($runId !== null && Schema::hasColumn('job_heartbeats', 'run_id')) {
            $data['run_id'] = $runId;
        }

        return static::create($data);
    }

    /**
     * Record job start.
     */
    public static function started(string|object $job, array $context = [], ?string $runId = null): ?static
    {
        return static::record($job, 'started', $context, $runId);
    }

    /**
     * Record job completion.
     */
    public static function completed(string|object $job, array $context = [], ?string $runId = null): ?static
    {
        return static::record($job, 'completed', $context, $runId);
    }

    /**
     * Record job failure.
     */
    public static function failed(string|object $job, string $error, array $context = [], ?string $runId = null): ?static
    {
        return static::record($job, 'failed', array_merge($context, [
            'error' => substr($error, 0, 500),
        ]), $runId);
    }

    /**
     * Record progress update.
     */
    public static function progress(string|object $job, int $current, int $total, array $context = [], ?string $runId = null): ?static
    {
        return static::record($job, 'progress', array_merge($context, [
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round(($current / $total) * 100, 1) : 0,
        ]), $runId);
    }

    /**
     * Get recent job runs (grouped by run_id) for dashboard display.
     */
    public static function recentRuns(int $limit = 15): array
    {
        if (! Schema::hasTable('job_heartbeats')) {
            return [];
        }

        // Check if run_id column exists
        $hasRunId = Schema::hasColumn('job_heartbeats', 'run_id');

        if (!$hasRunId) {
            // Fallback to ungrouped list
            return static::recent($limit);
        }

        // Get the most recent run_ids
        $recentRunIds = static::query()
            ->whereNotNull('run_id')
            ->select('run_id')
            ->groupBy('run_id')
            ->orderByRaw('MAX(created_at) DESC')
            ->limit($limit)
            ->pluck('run_id');

        if ($recentRunIds->isEmpty()) {
            return [];
        }

        // Get all heartbeats for these runs
        $heartbeats = static::query()
            ->whereIn('run_id', $recentRunIds)
            ->orderBy('created_at')
            ->get();

        // Group by run_id and build run summaries
        $runs = [];
        foreach ($heartbeats->groupBy('run_id') as $runId => $runHeartbeats) {
            $first = $runHeartbeats->first();
            $last = $runHeartbeats->last();

            $status = 'running';
            $error = null;
            $duration = null;

            foreach ($runHeartbeats as $hb) {
                if ($hb->metric === 'completed') {
                    $status = 'completed';
                } elseif ($hb->metric === 'failed') {
                    $status = 'failed';
                    $error = $hb->context['error'] ?? null;
                }
            }

            // Calculate duration if we have start and end
            $startTime = $runHeartbeats->firstWhere('metric', 'started')?->created_at;
            $endTime = $runHeartbeats->firstWhere('metric', 'completed')?->created_at
                ?? $runHeartbeats->firstWhere('metric', 'failed')?->created_at;

            if ($startTime && $endTime) {
                $duration = $endTime->diffInSeconds($startTime);
            }

            $runs[] = [
                'run_id' => $runId,
                'job' => $first->job,
                'status' => $status,
                'error' => $error,
                'started_at' => $startTime?->toIso8601String(),
                'started_at_human' => $startTime?->diffForHumans(),
                'duration' => $duration,
                'duration_human' => $duration !== null ? static::formatDurationSeconds($duration) : null,
                'events' => $runHeartbeats->map(fn($h) => [
                    'metric' => $h->metric,
                    'context' => $h->context,
                    'at' => $h->created_at?->toIso8601String(),
                ])->toArray(),
            ];
        }

        // Sort by started_at descending
        usort($runs, fn($a, $b) => strcmp($b['started_at'] ?? '', $a['started_at'] ?? ''));

        return $runs;
    }

    /**
     * Format seconds into human-readable duration.
     */
    protected static function formatDurationSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes < 60) {
            return $secs > 0 ? "{$minutes}m {$secs}s" : "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
    }

    /**
     * Get recent heartbeats for dashboard display (ungrouped).
     */
    public static function recent(int $limit = 20): array
    {
        if (! Schema::hasTable('job_heartbeats')) {
            return [];
        }

        return static::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'job' => $h->job,
                'run_id' => $h->run_id ?? null,
                'metric' => $h->metric,
                'context' => $h->context,
                'at' => $h->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get summary of recent job activity.
     */
    public static function summary(int $minutes = 60): array
    {
        if (! Schema::hasTable('job_heartbeats')) {
            return ['available' => false];
        }

        $since = now()->subMinutes($minutes);

        $counts = static::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('job, metric, COUNT(*) as count')
            ->groupBy('job', 'metric')
            ->get()
            ->groupBy('job')
            ->map(fn($group) => $group->pluck('count', 'metric')->toArray())
            ->toArray();

        $lastActivity = static::query()
            ->orderByDesc('created_at')
            ->first();

        return [
            'available' => true,
            'since_minutes' => $minutes,
            'jobs' => $counts,
            'last_activity' => $lastActivity?->created_at?->toIso8601String(),
            'last_job' => $lastActivity?->job,
        ];
    }

    /**
     * Prune old heartbeats to prevent table bloat.
     */
    public static function prune(int $keepDays = 7): int
    {
        if (! Schema::hasTable('job_heartbeats')) {
            return 0;
        }

        return static::query()
            ->where('created_at', '<', now()->subDays($keepDays))
            ->delete();
    }
}
