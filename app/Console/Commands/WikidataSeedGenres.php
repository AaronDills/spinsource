<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Genre;
use App\Support\Sparql;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WikidataSeedGenres extends Command
{
    protected $signature = 'wikidata:seed-genres
        {--limit=0 : Max rows to process total (0 = no limit)}
        {--page-size=100 : SPARQL page size}
        {--dry-run : Do not write to DB, only report}
        {--offset=0 : Start offset for SPARQL pagination}';

    protected $description = 'Seed musical genres (and related country metadata) from Wikidata via SPARQL';

    public function handle(): int
    {
        $endpoint = config('wikidata.endpoint');
        $ua = config('wikidata.user_agent');

        $limit    = (int) $this->option('limit');
        $pageSize = max(25, min(2000, (int) $this->option('page-size')));
        $offset   = max(0, (int) $this->option('offset'));
        $dryRun   = (bool) $this->option('dry-run');

        $totalProcessed = 0;

        $this->info(
            "Seeding genres from Wikidata (pageSize={$pageSize}, offset={$offset}, dryRun=" .
            ($dryRun ? 'yes' : 'no') . ')'
        );

        while (true) {
            // Respect overall --limit
            $currentPageSize = $pageSize;
            if ($limit > 0) {
                $remaining = $limit - $totalProcessed;
                if ($remaining <= 0) {
                    break;
                }
                $currentPageSize = min($currentPageSize, $remaining);
            }

            // Make dry-runs intentionally cheap
            if ($dryRun) {
                $currentPageSize = min($currentPageSize, 25);
            }

            $sparql = Sparql::load('genres', [
                'limit'  => $currentPageSize,
                'offset' => $offset,
            ]);

            try {
                $response = Http::withHeaders([
                        'Accept'            => 'application/sparql-results+json',
                        'User-Agent'        => $ua,
                        'Accept-Encoding'   => 'gzip',
                        'Content-Type'      => 'application/x-www-form-urlencoded',
                    ])
                    ->connectTimeout(10)
                    ->timeout(120)
                    ->retry(4, 1500)
                    ->asForm()
                    ->post($endpoint, [
                        'format' => 'json',
                        'query'  => $sparql,
                    ])
                    ->throw();
            } catch (RequestException $e) {
                $status = optional($e->response)->status();

                $this->error('Wikidata request failed.');
                if ($status) {
                    $this->line("HTTP status: {$status}");
                }
                $this->line($e->getMessage());

                if (in_array($status, [429, 503], true)) {
                    $this->warn('Wikidata Query Service may be throttling or under load. Try a smaller --page-size.');
                }

                return self::FAILURE;
            }

            $bindings = $response->json('results.bindings', []);
            if (count($bindings) === 0) {
                $this->info('No more results. Done.');
                break;
            }

            // Track parent relationships to resolve after upserts
            $pendingParents = [];

            foreach ($bindings as $row) {
                $genreQid = $this->qidFromEntityUrl(data_get($row, 'genre.value'));
                if (! $genreQid) {
                    continue;
                }

                $name        = data_get($row, 'genreLabel.value');
                $description = data_get($row, 'genreDescription.value');
                $mbid        = data_get($row, 'musicBrainzId.value');
                $inception   = $this->extractYear(data_get($row, 'inception.value'));

                // Country
                $countryId   = null;
                $countryQid  = $this->qidFromEntityUrl(data_get($row, 'country.value'));
                $countryName = data_get($row, 'countryLabel.value');

                if ($countryQid && $countryName && ! $dryRun) {
                    $country = Country::updateOrCreate(
                        ['wikidata_id' => $countryQid],
                        ['name' => $countryName]
                    );
                    $countryId = $country->id;
                }

                // Parent genre
                $parentQid = $this->qidFromEntityUrl(data_get($row, 'parentGenre.value'));
                if ($parentQid) {
                    $pendingParents[$genreQid] = $parentQid;
                }

                $payload = array_filter([
                    'name'            => $name,
                    'description'     => $description,
                    'musicbrainz_id'  => $mbid,
                    'inception_year'  => $inception,
                    'country_id'      => $countryId,
                ], static fn ($v) => $v !== null);

                if ($dryRun) {
                    $this->line("DRY: upsert genre {$genreQid} ({$name})");
                } else {
                    Genre::updateOrCreate(
                        ['wikidata_id' => $genreQid],
                        $payload
                    );
                }

                $totalProcessed++;
            }

            // Resolve parent_genre_id relationships
            if (! $dryRun && count($pendingParents) > 0) {
                $this->resolveParents($pendingParents);
            }

            $this->info(
                "Processed page offset={$offset} count=" .
                count($bindings) .
                " (totalProcessed={$totalProcessed})"
            );

            $offset += $currentPageSize;

            // Gentle throttle to keep WDQS happy
            usleep(250_000);
        }

        $this->info("Finished. Total processed: {$totalProcessed}");
        return self::SUCCESS;
    }

    private function qidFromEntityUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $pos = strrpos($url, '/');
        if ($pos === false) {
            return null;
        }

        $qid = substr($url, $pos + 1);
        return preg_match('/^Q\d+$/', $qid) ? $qid : null;
    }

    private function extractYear(?string $dateValue): ?int
    {
        if (! $dateValue) {
            return null;
        }

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
        $childQids  = array_keys($pendingParents);
        $parentQids = array_values($pendingParents);

        $genres = Genre::query()
            ->whereIn('wikidata_id', array_merge($childQids, $parentQids))
            ->get()
            ->keyBy('wikidata_id');

        foreach ($pendingParents as $childQid => $parentQid) {
            $child  = $genres->get($childQid);
            $parent = $genres->get($parentQid);

            if (! $child || ! $parent) {
                // Parent may not be seeded yet; resolve on later runs
                continue;
            }

            if ($child->parent_genre_id !== $parent->id) {
                $child->parent_genre_id = $parent->id;
                $child->save();
            }
        }
    }
}
