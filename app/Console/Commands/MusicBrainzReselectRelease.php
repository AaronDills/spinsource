<?php

namespace App\Console\Commands;

use App\Jobs\MusicBrainzFetchTracklist;
use App\Models\Album;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MusicBrainzReselectRelease extends Command
{
    protected $signature = 'musicbrainz:reselect-release
        {albumId? : Album ID to reselect release for (omit for all)}
        {--force : Force re-selection even if a release is already selected}
        {--chunk-size=100 : Albums to process per chunk (for batch mode)}';

    protected $description = 'Re-pick default MusicBrainz releases for album tracklists';

    public function handle(): int
    {
        $albumId = $this->argument('albumId');
        $force = $this->option('force');
        $chunkSize = max(10, min(500, (int) $this->option('chunk-size')));

        if ($albumId) {
            return $this->processAlbum((int) $albumId, $force);
        }

        return $this->processAllAlbums($force, $chunkSize);
    }

    private function processAlbum(int $albumId, bool $force): int
    {
        $album = Album::find($albumId);

        if (! $album) {
            $this->error("Album {$albumId} not found.");

            return self::FAILURE;
        }

        if (! $album->musicbrainz_release_group_mbid) {
            $this->error("Album {$albumId} has no MusicBrainz release group ID.");

            return self::FAILURE;
        }

        if ($album->selected_release_mbid && ! $force) {
            $this->warn("Album {$albumId} already has a selected release: {$album->selected_release_mbid}");
            $this->info('Use --force to re-select.');

            return self::SUCCESS;
        }

        // Clear the selected release if forcing, so it will be re-selected
        if ($force && $album->selected_release_mbid) {
            $this->info("Clearing existing selected release: {$album->selected_release_mbid}");
            $album->update(['selected_release_mbid' => null]);
        }

        MusicBrainzFetchTracklist::dispatch($album->id, forceReselect: true);

        $this->info("Dispatched reselection job for album {$albumId} ({$album->title})");

        Log::info('MusicBrainz release reselection dispatched', [
            'albumId' => $albumId,
            'albumTitle' => $album->title,
            'force' => $force,
        ]);

        return self::SUCCESS;
    }

    private function processAllAlbums(bool $force, int $chunkSize): int
    {
        $query = Album::whereNotNull('musicbrainz_release_group_mbid');

        if (! $force) {
            // Only process albums without a selected release
            $query->whereNull('selected_release_mbid');
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No albums to process.');
            $this->info('Use --force to reselect releases for albums that already have one.');

            return self::SUCCESS;
        }

        $this->info("Processing {$total} albums...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $dispatched = 0;

        $query->chunkById($chunkSize, function ($albums) use (&$dispatched, $bar, $force) {
            foreach ($albums as $album) {
                // Clear the selected release if forcing
                if ($force && $album->selected_release_mbid) {
                    $album->update(['selected_release_mbid' => null]);
                }

                MusicBrainzFetchTracklist::dispatch($album->id, forceReselect: true);
                $dispatched++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Dispatched {$dispatched} reselection jobs.");

        Log::info('MusicBrainz batch release reselection dispatched', [
            'albumsProcessed' => $dispatched,
            'force' => $force,
        ]);

        return self::SUCCESS;
    }
}
