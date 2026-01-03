<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
     * @return static|null
     */
    public static function record(string|object $job, string $metric = 'heartbeat', array $context = []): ?static
    {
        // Gracefully handle missing table
        if (! Schema::hasTable('job_heartbeats')) {
            return null;
        }

        $jobName = is_object($job) ? get_class($job) : $job;

        // Extract short class name for readability
        $shortName = class_basename($jobName);

        return static::create([
            'job' => $shortName,
            'metric' => $metric,
            'context' => $context,
        ]);
    }

    /**
     * Record job start.
     */
    public static function started(string|object $job, array $context = []): ?static
    {
        return static::record($job, 'started', $context);
    }

    /**
     * Record job completion.
     */
    public static function completed(string|object $job, array $context = []): ?static
    {
        return static::record($job, 'completed', $context);
    }

    /**
     * Record job failure.
     */
    public static function failed(string|object $job, string $error, array $context = []): ?static
    {
        return static::record($job, 'failed', array_merge($context, [
            'error' => substr($error, 0, 500),
        ]));
    }

    /**
     * Record progress update.
     */
    public static function progress(string|object $job, int $current, int $total, array $context = []): ?static
    {
        return static::record($job, 'progress', array_merge($context, [
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round(($current / $total) * 100, 1) : 0,
        ]));
    }

    /**
     * Get recent heartbeats for dashboard display.
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
