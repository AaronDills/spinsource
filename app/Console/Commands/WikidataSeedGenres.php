<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Genre;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WikidataSeedGenres extends Command
{
    protected $signature = 'wikidata:seed-genres
        {--limit=0 : Max rows to process total (0 = no limit)}
        {--page-size=500 : SPARQL page size}
        {--dry-run : Do not write to DB, only report}
        {--offset=0 : Start offset for SPARQL pagination}';

    protected $description = 'Seed musical genres (and related country metadata) from Wikidata via SPARQL';

    public function handle(): int
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $limit = (int) $this->option('limit');
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $offset = max(0, (int) $this->option('offset'));
        $dryRun = (bool) $this->option('dry-run');

        $totalProcessed = 0;

        $this->info("Seeding genres from Wikidata (pageSize={$pageSize}, offset={$offset}, dryRun=" . ($dryRun ? 'yes' : 'no') . ")");

        while (true) {
            // Respect overall --limit
            $currentPageSize = $pageSize;
            if ($limit > 0) {
                $remaining = $limit - $totalProcessed;
                if ($remaining <= 0) break;
                $currentPageSize = min($currentPageSize, $remaining);
            }

            $sparql = Sparql::load('genres', [
                'limit' => $currentPageSize,
                'offset' => $offset,
            ]);

            $resp = Http::withHeaders([
                    'Accept' => 'application/sparql-results+json',
                    'User-Agent' => $ua,
                ])
                ->retry(3, 800, throw: false)
                ->timeout(30)
                ->get($endpoint, ['query' => $sparql]);

            if (! $resp->ok()) {
                $this->error("Wikidata request failed (HTTP {$resp->status()}).");
                $this->line($resp->body());
                return self::FAILURE;
            }

            $bindings = $resp->json('results.bindings', []);
            if (count($bindings) === 0) {
                $this->info('No more results. Done.');
                break;
            }

            // Keep parent relations to attempt linking once the genre rows exist
            $pendingParents = [];

            foreach ($bindings as $row) {
                $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
                if (! $genreQid) continue;

                $name = data_get($row, 'genreLabel.value');
                $description = data_get($row, 'genreDescription.value');
                $mbid = data_get($row, 'musicBrainzId.value');

                $inceptionYear = $this->extractYear(data_get($row, 'inception.value'));

                // Country
                $countryId = null;
                $countryQid = $this->qidFromEntityUrl(data_get($row, 'country.value'));
                $countryName = data_get($row, 'countryLabel.value');

                if ($countryQid && $countryName && ! $dryRun) {
                    $country = Country::updateOrCreate(
                        ['wikidata_id' => $countryQid],
                        ['name' => $countryName]
                    );
                    $countryId = $country->id;
                }

                // Parent genre (may not exist yet in DB on first pass)
                $parentQid = $this->qidFromEntityUrl(data_get($row, 'parentGenre.value'));
                if ($parentQid) {
                    $pendingParents[$genreQid] = $parentQid;
                }

                // Build update payload, filtered to avoid overwriting with nulls
                $payload = array_filter([
                    'name' => $name,
                    'description' => $description,
                    'musicbrainz_id' => $mbid,
                    'inception_year' => $inceptionYear,
                    'country_id' => $countryId,
                ], fn ($v) => $v !== null);

                if ($dryRun) {
                    $this->line("DRY: upsert genre {$genreQid} ({$name})");
                } else {
                    Genre::updateOrCreate(['wikidata_id' => $genreQid], $payload);
                }

                $totalProcessed++;
            }

            // Attempt to resolve parent_genre_id relationships for this page
            if (! $dryRun && count($pendingParents) > 0) {
                $this->resolveParents($pendingParents);
            }

            $this->info("Processed page offset={$offset} count=" . count($bindings) . " (totalProcessed={$totalProcessed})");

            $offset += $currentPageSize;

            // Gentle throttle; Wikidata appreciates it (prevents 429s)
            usleep(250_000);
        }

        $this->info("Finished. Total processed: {$totalProcessed}");
        return self::SUCCESS;
    }

    private function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) return null;

        // Typical: http://www.wikidata.org/entity/Q12345
        $pos = strrpos($url, '/');
        if ($pos === false) return null;

        $qid = substr($url, $pos + 1);
        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }

    private function extractYear(?string $dateValue): ?int
    {
        if (! $dateValue) return null;

        // Common format: +1977-01-01T00:00:00Z
        $clean = ltrim($dateValue, '+');

        try {
            return Carbon::parse($clean)->year;
        } catch (\Throwable) {
            if (preg_match('/(\d{4})/', $clean, $m)) {
                return (int) $m[1];
            }
            return null;
        }
    }

    private function resolveParents(array $pendingParents): void
    {
        // pendingParents: [childQid => parentQid]
        $childQids = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_id', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_id');

        foreach ($pendingParents as $childQid => $parentQid) {
            $child = $genres->get($childQid);
            $parent = $genres->get($parentQid);

            if (! $child || ! $parent) {
                // Parent not yet seeded; it will resolve on later runs.
                continue;
            }

            if ($child->parent_genre_id !== $parent->id) {
                $child->parent_genre_id = $parent->id;
                $child->save();
            }
        }
    }
}
