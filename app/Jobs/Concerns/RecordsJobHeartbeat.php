<?php

namespace App\Jobs\Concerns;

use App\Models\JobHeartbeat;

trait RecordsJobHeartbeat
{
    protected function recordHeartbeat(string $job, string $metric = null, array $context = []): void
    {
        try {
            JobHeartbeat::create([
                'job' => $job,
                'metric' => $metric,
                'context' => $context,
            ]);
        } catch (\Throwable $e) {
            // Swallow any errors — monitoring must not break jobs
        }
    }
}
