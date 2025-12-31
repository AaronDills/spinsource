<?php

namespace App\Console\Commands;

use App\Jobs\Incremental\DiscoverChangedArtists;
use App\Jobs\Incremental\DiscoverChangedGenres;
use App\Jobs\Incremental\DiscoverNewArtistIds;
use App\Jobs\Incremental\DiscoverNewGenres;
use App\Jobs\Incremental\RefreshAlbumsForChangedArtists;
use App\Models\IngestionCheckpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WikidataSyncWeekly extends Command
{
    protected $signature = 'wikidata:sync-weekly
        {--genres : Only sync genres}
        {--artists : Only sync artists}
        {--albums : Only refresh albums for changed artists}
        {--skip-new : Skip discovery of new entities}
        {--skip-changed : Skip discovery of changed entities}';

    protected $description = 'Run weekly incremental Wikidata sync. Discovers new and changed entities since last run and dispatches enrichment jobs. Much faster than full backfill.';

    public function handle(): int
    {
        $onlyGenres  = $this->option('genres');
        $onlyArtists = $this->option('artists');
        $onlyAlbums  = $this->option('albums');
        $skipNew     = $this->option('skip-new');
        $skipChanged = $this->option('skip-changed');

        // If none specified, run all
        $runAll = !$onlyGenres && !$onlyArtists && !$onlyAlbums;

        $this->info('Starting weekly incremental Wikidata sync...');
        $this->newLine();

        $dispatched = [];

        // 1. Genres (run first as artists may reference genres)
        if ($runAll || $onlyGenres) {
            $genreCheckpoint = IngestionCheckpoint::forKey('genres');

            if (!$skipNew) {
                $this->info('Dispatching: Discover new genres');
                DiscoverNewGenres::dispatch();
                $dispatched[] = 'DiscoverNewGenres';
            }

            if (!$skipChanged) {
                $this->info('Dispatching: Discover changed genres');
                $this->line("  Last changed at: " . ($genreCheckpoint->last_changed_at?->toIso8601String() ?? 'never'));
                DiscoverChangedGenres::dispatch();
                $dispatched[] = 'DiscoverChangedGenres';
            }
        }

        // 2. Artists
        if ($runAll || $onlyArtists) {
            $artistCheckpoint = IngestionCheckpoint::forKey('artists');

            if (!$skipNew) {
                $this->info('Dispatching: Discover new artists');
                $this->line("  Last seen O-ID: " . ($artistCheckpoint->last_seen_oid ?? 'none'));
                DiscoverNewArtistIds::dispatch();
                $dispatched[] = 'DiscoverNewArtistIds';
            }

            if (!$skipChanged) {
                $this->info('Dispatching: Discover changed artists');
                $this->line("  Last changed at: " . ($artistCheckpoint->last_changed_at?->toIso8601String() ?? 'never'));
                DiscoverChangedArtists::dispatch();
                $dispatched[] = 'DiscoverChangedArtists';
            }
        }

        // 3. Albums (driven by changed artists)
        if ($runAll || $onlyAlbums) {
            $this->info('Dispatching: Refresh albums for changed artists');
            RefreshAlbumsForChangedArtists::dispatch();
            $dispatched[] = 'RefreshAlbumsForChangedArtists';
        }

        $this->newLine();
        $this->info('Weekly sync dispatched ' . count($dispatched) . ' job(s):');
        foreach ($dispatched as $job) {
            $this->line("  - {$job}");
        }

        Log::info('Weekly Wikidata sync dispatched', [
            'jobs' => $dispatched,
        ]);

        $this->newLine();
        $this->info('Jobs are now processing in the background queue.');
        $this->line('Monitor progress with: php artisan queue:listen');

        return self::SUCCESS;
    }
}
