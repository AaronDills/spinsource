<?php

namespace App\Console\Commands;

use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArtistsRecomputeMetrics extends Command
{
    protected $signature = 'artists:recompute-metrics
        {--chunk-size=1000 : Artists to process per chunk}
        {--reindex : Trigger Scout reindex after updating}';

    protected $description = 'Recompute album_count and link_count metrics for all artists';

    public function handle(): int
    {
        $chunkSize = max(100, min(5000, (int) $this->option('chunk-size')));
        $reindex = $this->option('reindex');

        $this->info('Computing album_count for all artists...');

        // Bulk update album_count using a single SQL statement
        $albumCountUpdated = DB::statement('
            UPDATE artists
            SET album_count = (
                SELECT COUNT(*)
                FROM albums
                WHERE albums.artist_id = artists.id
            )
        ');

        $this->info('Computing link_count for all artists...');

        // Bulk update link_count using a single SQL statement
        $linkCountUpdated = DB::statement('
            UPDATE artists
            SET link_count = (
                SELECT COUNT(*)
                FROM artist_links
                WHERE artist_links.artist_id = artists.id
            )
        ');

        $totalArtists = Artist::count();
        $this->info("Updated metrics for {$totalArtists} artists.");

        // Show some stats
        $stats = Artist::selectRaw('
            AVG(album_count) as avg_albums,
            MAX(album_count) as max_albums,
            AVG(link_count) as avg_links,
            MAX(link_count) as max_links,
            SUM(CASE WHEN wikipedia_url IS NOT NULL THEN 1 ELSE 0 END) as with_wikipedia,
            SUM(CASE WHEN spotify_artist_id IS NOT NULL THEN 1 ELSE 0 END) as with_spotify,
            SUM(CASE WHEN musicbrainz_id IS NOT NULL THEN 1 ELSE 0 END) as with_musicbrainz
        ')->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Average albums per artist', number_format($stats->avg_albums ?? 0, 2)],
                ['Max albums', $stats->max_albums ?? 0],
                ['Average links per artist', number_format($stats->avg_links ?? 0, 2)],
                ['Max links', $stats->max_links ?? 0],
                ['Artists with Wikipedia', $stats->with_wikipedia ?? 0],
                ['Artists with Spotify ID', $stats->with_spotify ?? 0],
                ['Artists with MusicBrainz ID', $stats->with_musicbrainz ?? 0],
            ]
        );

        if ($reindex) {
            $this->info('Triggering Scout reindex...');

            $bar = $this->output->createProgressBar($totalArtists);
            $bar->start();

            Artist::chunkById($chunkSize, function ($artists) use ($bar) {
                $artists->searchable();
                $bar->advance($artists->count());
            });

            $bar->finish();
            $this->newLine();
            $this->info('Reindex complete.');
        }

        Log::info('Artist metrics recomputed', [
            'totalArtists' => $totalArtists,
            'avgAlbums' => $stats->avg_albums ?? 0,
            'avgLinks' => $stats->avg_links ?? 0,
            'reindexed' => $reindex,
        ]);

        return self::SUCCESS;
    }
}
