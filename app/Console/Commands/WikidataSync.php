<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class WikidataSync extends Command
{
    protected $signature = 'wikidata:sync
        {--force : Run even if a lock exists}
        {--page-size=500 : Page size for Wikidata seed jobs}
        {--genres-after= : Start genres after this QID (e.g. Q12345)}';

    protected $description = 'Sync reference + core music data from Wikidata';

    public function handle(): int
    {
        // If your full sync can exceed an hour, increase this.
        $lock = Cache::lock('wikidata:sync', 6 * 60 * 60); // 6 hour lock

        if (! $this->option('force') && ! $lock->get()) {
            $this->warn('Another wikidata:sync is already running. Exiting.');
            return self::SUCCESS;
        }

        try {
            $this->info('Starting Wikidata sync...');

            $pageSize = max(25, min(2000, (int) $this->option('page-size')));

            $steps = [
                [
                    'cmd' => 'wikidata:dispatch-seed-genres',
                    'args' => array_filter([
                        '--page-size' => $pageSize,
                        '--after-qid' => $this->option('genres-after') ?: null, // start from beginning if null
                    ], fn ($v) => $v !== null),
                ],
                [
                    'cmd' => 'wikidata:dispatch-seed-artists',
                    'args' => [
                        '--page-size' => $pageSize,
                        // (add artist cursor options similarly when you convert it)
                    ],
                ],
            ];

            foreach ($steps as $step) {
                $cmd = $step['cmd'];
                $args = $step['args'] ?? [];

                $this->line('→ Running: ' . $cmd . ($args ? ' ' . json_encode($args) : ''));
                $exit = Artisan::call($cmd, $args, $this->output);

                if ($exit !== self::SUCCESS) {
                    $this->error("Command failed: {$cmd}");
                    return $exit;
                }
            }

            $this->info('Wikidata sync dispatched successfully (jobs will continue on the queue).');
            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }
}
