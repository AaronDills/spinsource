<?php

namespace App\Console\Commands;

use App\Jobs\WikidataEnrichArtists;
use App\Models\Artist;
use Illuminate\Console\Command;

class WikidataFixArtistNames extends Command
{
    protected $signature = 'wikidata:fix-artist-names
        {--batch-size=100 : QIDs per enrich job}
        {--dry-run : Show what would be fixed without dispatching jobs}';

    protected $description = 'Re-enrich artists whose name or sort_name are stored as Q-IDs';

    public function handle(): int
    {
        $batchSize = max(10, min(500, (int) $this->option('batch-size')));
        $dryRun = $this->option('dry-run');

        // Find artists where name or sort_name looks like a Q-ID (Q followed by digits)
        $artists = Artist::where(function ($query) {
                $query->whereRaw("name REGEXP '^Q[0-9]+$'")
                      ->orWhereRaw("sort_name REGEXP '^Q[0-9]+$'");
            })
            ->whereNotNull('wikidata_id')
            ->get(['id', 'name', 'sort_name', 'wikidata_id']);

        $count = $artists->count();

        if ($count === 0) {
            $this->info('No artists found with Q-ID names.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} artists with Q-ID names.");

        if ($dryRun) {
            $this->table(
                ['ID', 'Current Name', 'Sort Name', 'Wikidata ID'],
                $artists->take(20)->map(fn ($a) => [$a->id, $a->name, $a->sort_name, $a->wikidata_id])->toArray()
            );
            if ($count > 20) {
                $this->info("... and " . ($count - 20) . " more.");
            }
            $this->info('Run without --dry-run to dispatch enrichment jobs.');
            return self::SUCCESS;
        }

        $qids = $artists->pluck('wikidata_id')->toArray();
        $chunks = array_chunk($qids, $batchSize);

        foreach ($chunks as $chunk) {
            WikidataEnrichArtists::dispatch($chunk);
        }

        $jobCount = count($chunks);
        $this->info("Dispatched {$jobCount} enrichment job(s) for {$count} artists.");
        $this->info('After jobs complete, run: php artisan scout:import "App\Models\Artist"');

        return self::SUCCESS;
    }
}
