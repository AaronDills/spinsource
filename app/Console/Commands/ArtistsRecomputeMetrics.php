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
        {--reindex : Trigger Scout reindex after updating}
        {--quality-only : Only recompute quality_score (skip album/link counts)}';

    protected $description = 'Recompute album_count, link_count, and quality_score metrics for all artists';

    public function handle(): int
    {
        $chunkSize = max(100, min(5000, (int) $this->option('chunk-size')));
        $reindex = $this->option('reindex');
        $qualityOnly = $this->option('quality-only');

        if (! $qualityOnly) {
            $this->info('Computing album_count for all artists...');

            // Bulk update album_count using a single SQL statement
            DB::statement('
                UPDATE artists
                SET album_count = (
                    SELECT COUNT(*)
                    FROM albums
                    WHERE albums.artist_id = artists.id
                )
            ');

            $this->info('Computing link_count for all artists...');

            // Bulk update link_count using a single SQL statement
            DB::statement('
                UPDATE artists
                SET link_count = (
                    SELECT COUNT(*)
                    FROM artist_links
                    WHERE artist_links.artist_id = artists.id
                )
            ');
        }

        $this->info('Computing quality_score for all artists...');
        $this->computeQualityScores($chunkSize);

        $totalArtists = Artist::count();
        $this->info("Updated metrics for {$totalArtists} artists.");

        // Show some stats
        $stats = Artist::selectRaw('
            AVG(album_count) as avg_albums,
            MAX(album_count) as max_albums,
            AVG(link_count) as avg_links,
            MAX(link_count) as max_links,
            AVG(quality_score) as avg_quality,
            MAX(quality_score) as max_quality,
            MIN(quality_score) as min_quality,
            SUM(CASE WHEN wikipedia_url IS NOT NULL THEN 1 ELSE 0 END) as with_wikipedia,
            SUM(CASE WHEN spotify_artist_id IS NOT NULL THEN 1 ELSE 0 END) as with_spotify,
            SUM(CASE WHEN musicbrainz_artist_mbid IS NOT NULL THEN 1 ELSE 0 END) as with_musicbrainz
        ')->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Average albums per artist', number_format($stats->avg_albums ?? 0, 2)],
                ['Max albums', $stats->max_albums ?? 0],
                ['Average links per artist', number_format($stats->avg_links ?? 0, 2)],
                ['Max links', $stats->max_links ?? 0],
                ['Average quality score', number_format($stats->avg_quality ?? 0, 1)],
                ['Quality score range', ($stats->min_quality ?? 0).' - '.($stats->max_quality ?? 0)],
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
            'avgQuality' => $stats->avg_quality ?? 0,
            'reindexed' => $reindex,
        ]);

        return self::SUCCESS;
    }

    /**
     * Compute and store quality_score for all artists in chunks.
     */
    private function computeQualityScores(int $chunkSize): void
    {
        $bar = $this->output->createProgressBar(Artist::count());
        $bar->start();

        Artist::select([
            'id',
            'wikipedia_url',
            'description',
            'image_commons',
            'official_website',
            'spotify_artist_id',
            'apple_music_artist_id',
            'musicbrainz_artist_mbid',
            'discogs_artist_id',
            'album_count',
            'link_count',
        ])->chunkById($chunkSize, function ($artists) use ($bar) {
            $updates = [];

            foreach ($artists as $artist) {
                $updates[] = [
                    'id' => $artist->id,
                    'quality_score' => Artist::computeQualityScore($artist->toArray()),
                ];
            }

            // Batch update using CASE statement for efficiency
            if (! empty($updates)) {
                $cases = [];
                $ids = [];

                foreach ($updates as $update) {
                    $cases[] = "WHEN {$update['id']} THEN {$update['quality_score']}";
                    $ids[] = $update['id'];
                }

                $caseSql = implode(' ', $cases);
                $idsSql = implode(',', $ids);

                DB::statement("
                    UPDATE artists
                    SET quality_score = CASE id {$caseSql} END
                    WHERE id IN ({$idsSql})
                ");
            }

            $bar->advance($artists->count());
        });

        $bar->finish();
        $this->newLine();
    }
}
