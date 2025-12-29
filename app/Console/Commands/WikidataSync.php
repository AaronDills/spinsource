<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class WikidataSync extends Command
{
    protected $signature = 'wikidata:sync {--force : Run even if a lock exists}';
    protected $description = 'Sync reference + core music data from Wikidata';

    public function handle(): int
    {
        $lock = Cache::lock('wikidata:sync', 60 * 60); // 1 hour lock

        if (! $this->option('force') && ! $lock->get()) {
            $this->warn('Another wikidata:sync is already running. Exiting.');
            return self::SUCCESS;
        }

        try {
            $this->info('Starting Wikidata sync...');

            $steps = [
                // Countries are created on-demand by other seeders; a dedicated pass is optional.
                'wikidata:seed-genres',
                // Next steps (to be added as we implement them):
                // 'wikidata:seed-artists',
                // 'wikidata:seed-albums',
            ];

            foreach ($steps as $cmd) {
                $this->line("→ Running: {$cmd}");
                $exit = Artisan::call($cmd, [], $this->output);

                if ($exit !== self::SUCCESS) {
                    $this->error("Command failed: {$cmd}");
                    return $exit;
                }
            }

            $this->info('Wikidata sync complete.');
            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }
}
