<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AlbumsRecomputeQualityScore extends Command
{
    protected $signature = 'albums:recompute-quality-score
        {--chunk-size=1000 : Albums to process per chunk}
        {--reindex : Trigger Scout reindex after updating}';

    protected $description = 'Recompute quality_score for all albums';

    public function handle(): int
    {
        $chunkSize = max(100, min(5000, (int) $this->option('chunk-size')));
        $reindex = $this->option('reindex');

        $this->info('Computing quality_score for all albums...');

        // Pre-fetch artist quality scores for efficiency (this is ~330K entries, ~10MB)
        $this->info('Loading artist quality scores...');
        $artistScores = Artist::pluck('quality_score', 'id')->toArray();
        $this->info('Loaded '.count($artistScores).' artist scores.');

        $processed = 0;

        Album::select([
            'id',
            'artist_id',
            'wikipedia_url',
            'cover_image_commons',
            'description',
            'tracklist_fetched_at',
            'spotify_album_id',
            'apple_music_album_id',
            'musicbrainz_release_group_mbid',
        ])->chunkById($chunkSize, function ($albums) use (&$processed, $artistScores) {
            $updates = [];

            foreach ($albums as $album) {
                $artistQualityScore = $artistScores[$album->artist_id] ?? null;
                $updates[] = [
                    'id' => $album->id,
                    'quality_score' => Album::computeQualityScore($album->toArray(), $artistQualityScore),
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
                    UPDATE albums
                    SET quality_score = CASE id {$caseSql} END
                    WHERE id IN ({$idsSql})
                ");
            }

            $processed += $albums->count();
            if ($processed % 10000 === 0) {
                $this->info("Processed {$processed} albums...");
            }
        });

        $totalAlbums = Album::count();
        $this->info("Updated quality_score for {$totalAlbums} albums.");

        // Show stats
        $stats = Album::selectRaw('
            AVG(quality_score) as avg_quality,
            MAX(quality_score) as max_quality,
            MIN(quality_score) as min_quality,
            SUM(CASE WHEN wikipedia_url IS NOT NULL THEN 1 ELSE 0 END) as with_wikipedia,
            SUM(CASE WHEN cover_image_commons IS NOT NULL THEN 1 ELSE 0 END) as with_cover,
            SUM(CASE WHEN spotify_album_id IS NOT NULL THEN 1 ELSE 0 END) as with_spotify
        ')->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Average quality score', number_format($stats->avg_quality ?? 0, 1)],
                ['Quality score range', ($stats->min_quality ?? 0).' - '.($stats->max_quality ?? 0)],
                ['Albums with Wikipedia', $stats->with_wikipedia ?? 0],
                ['Albums with cover image', $stats->with_cover ?? 0],
                ['Albums with Spotify ID', $stats->with_spotify ?? 0],
            ]
        );

        if ($reindex) {
            $this->info('Triggering Scout reindex...');

            $bar = $this->output->createProgressBar($totalAlbums);
            $bar->start();

            Album::chunkById($chunkSize, function ($albums) use ($bar) {
                $albums->searchable();
                $bar->advance($albums->count());
            });

            $bar->finish();
            $this->newLine();
            $this->info('Reindex complete.');
        }

        Log::info('Album quality scores recomputed', [
            'totalAlbums' => $totalAlbums,
            'avgQuality' => $stats->avg_quality ?? 0,
            'reindexed' => $reindex,
        ]);

        return self::SUCCESS;
    }
}
