<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Support\Sparql;
use Illuminate\Support\Facades\Log;

/**
 * Recompute sort_name for existing artists using Wikidata name components.
 * Lightweight job that only fetches given/family names and updates sort_name.
 */
class WikidataRecomputeSortNames extends WikidataJob
{
    /** @param array<int,string> $artistQids */
    public function __construct(public array $artistQids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $artistQids = array_values(array_unique(array_filter(
            $this->artistQids,
            fn ($q) => is_string($q) && preg_match('/^Q\d+$/', $q)
        )));

        if (count($artistQids) === 0) {
            return;
        }

        Log::info('Wikidata recompute sort_name batch start', [
            'count' => count($artistQids),
        ]);

        $values = implode(' ', array_map(fn ($qid) => "wd:$qid", $artistQids));

        $sparql = Sparql::load('artist_name_components', ['values' => $values]);
        $response = $this->executeWdqsRequest($sparql);

        if ($response === null) {
            return; // Rate limited, job released
        }

        $bindings = $response->json('results.bindings', []);

        // Build QID -> name components map
        $nameComponents = [];
        foreach ($bindings as $row) {
            $qid = $this->qidFromEntityUrl(data_get($row, 'artist.value'));
            if (!$qid) continue;

            // Only take first result per artist
            if (isset($nameComponents[$qid])) continue;

            $nameComponents[$qid] = [
                'givenName' => data_get($row, 'givenNameLabel.value'),
                'familyName' => data_get($row, 'familyNameLabel.value'),
            ];
        }

        // Load artists from database
        $artists = Artist::whereIn('wikidata_id', $artistQids)
            ->get(['id', 'wikidata_id', 'name', 'sort_name']);

        $updated = 0;
        foreach ($artists as $artist) {
            $components = $nameComponents[$artist->wikidata_id] ?? [];

            $newSortName = $this->computeSortName(
                $artist->name,
                $components['givenName'] ?? null,
                $components['familyName'] ?? null
            );

            if ($newSortName && $newSortName !== $artist->sort_name) {
                $artist->sort_name = $newSortName;
                $artist->save();
                $updated++;
            }
        }

        Log::info('Wikidata recompute sort_name batch done', [
            'requested' => count($artistQids),
            'updated' => $updated,
        ]);
    }

    /**
     * Compute a sortable name from display name and name components.
     * For persons with family name: "Family, Given"
     * For groups/bands: strip "The " prefix and lowercase
     */
    private function computeSortName(?string $displayName, ?string $givenName, ?string $familyName): ?string
    {
        $displayName = $displayName ? trim($displayName) : null;
        if (!$displayName) return null;

        $givenName = $givenName ? trim($givenName) : null;
        $familyName = $familyName ? trim($familyName) : null;

        // Skip Q-ID labels that leaked through
        if ($givenName && preg_match('/^Q\d+$/', $givenName)) $givenName = null;
        if ($familyName && preg_match('/^Q\d+$/', $familyName)) $familyName = null;

        // If we have family name, format as "Family, Given"
        if ($familyName) {
            return $givenName ? "{$familyName}, {$givenName}" : $familyName;
        }

        // For groups/unknowns: strip "The " prefix and lowercase for sorting
        $sortName = mb_strtolower($displayName);
        if (str_starts_with($sortName, 'the ')) {
            $sortName = trim(substr($sortName, 4));
        }

        return $sortName;
    }
}
