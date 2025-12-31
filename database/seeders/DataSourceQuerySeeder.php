<?php

namespace Database\Seeders;

use App\Models\DataSourceQuery;
use Illuminate\Database\Seeder;

class DataSourceQuerySeeder extends Seeder
{
    /**
     * Seed the data_source_queries table with existing SPARQL queries.
     */
    public function run(): void
    {
        $queries = $this->getQueries();

        foreach ($queries as $query) {
            DataSourceQuery::updateOrCreate(
                [
                    'name' => $query['name'],
                    'data_source' => $query['data_source'],
                ],
                [
                    'query_type' => $query['query_type'],
                    'query' => $query['query'],
                    'description' => $query['description'] ?? null,
                    'variables' => $query['variables'] ?? null,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Seeded '.count($queries).' data source queries.');
    }

    /**
     * Get all query definitions.
     */
    private function getQueries(): array
    {
        return [
            // Root level queries
            [
                'name' => 'genres',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Discover all genre entities from Wikidata with cursor-based pagination',
                'variables' => ['after_filter', 'limit'],
                'query' => <<<'SPARQL'
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT DISTINCT
  ?genre
  ?oid
  ?genreLabel
  ?genreDescription
  ?inception
  ?country ?countryLabel
  ?parentGenre ?parentGenreLabel
  ?musicBrainzId
WHERE {
  ?genre wdt:P31 wd:Q188451 .

  OPTIONAL { ?genre wdt:P571 ?inception . }
  OPTIONAL { ?genre wdt:P495 ?country . }
  OPTIONAL { ?genre wdt:P279 ?parentGenre . }
  OPTIONAL { ?genre wdt:P436 ?musicBrainzId . }

  # Bind numeric O-ID for cursor paging
  BIND(xsd:integer(STRAFTER(STR(?genre), "entity/Q")) AS ?oid)
  {{after_filter}}

  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
ORDER BY ?oid
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'albums',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Fetch albums for a batch of artists with Spotify and Apple Music IDs',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Fetch albums for a batch of artists - optimized for speed
# Removed expensive Wikipedia URL lookup

SELECT
  ?album
  ?albumLabel
  ?albumDescription
  ?artist
  ?albumType
  ?publicationDate
  ?musicBrainzReleaseGroupId
  ?spotifyAlbumId
  ?appleMusicAlbumId
  ?coverImage
WHERE {
  VALUES ?artist { {{values}} }

  ?album wdt:P175 ?artist .

  # Constrain to album-like items
  ?album wdt:P31 ?albumType .
  VALUES ?albumType {
    wd:Q482994    # studio album
    wd:Q169930    # EP
    wd:Q134556    # single
    wd:Q222910    # compilation album
    wd:Q209939    # live album
    wd:Q59481898  # remix album
    wd:Q24672043  # soundtrack album
  }

  OPTIONAL { ?album wdt:P577 ?publicationDate . }
  OPTIONAL { ?album wdt:P436 ?musicBrainzReleaseGroupId . }
  OPTIONAL { ?album wdt:P2205 ?spotifyAlbumId . }
  OPTIONAL { ?album wdt:P2281 ?appleMusicAlbumId . }
  OPTIONAL { ?album wdt:P18 ?coverImage . }

  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,de,fr,es,it,pt,nl,*" .
  }
}
SPARQL,
            ],
            [
                'name' => 'artist_ids',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Discover artist QIDs (humans who are musicians + musical groups) with cursor pagination',
                'variables' => ['after_filter', 'limit'],
                'query' => <<<'SPARQL'
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

# Discovery query for artist QIDs - kept simple to avoid timeouts
# Invalid entities (no labels) are filtered during enrichment step

SELECT DISTINCT ?artist ?oid WHERE {
  {
    # Humans who are musicians (broad: includes many solo artists)
    ?artist wdt:P31 wd:Q5 ;
            wdt:P106/wdt:P279* wd:Q639669 .
  }
  UNION
  {
    # Musical groups (including subclasses)
    ?artist wdt:P31/wdt:P279* wd:Q215380 .
  }

  # Bind numeric O-ID for cursor paging
  BIND(xsd:integer(STRAFTER(STR(?artist), "entity/Q")) AS ?oid)
  {{after_filter}}
}
ORDER BY ?oid
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'artist_enrich_basic',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Enrich artists with basic metadata (name, country, website, images, dates, MusicBrainz ID)',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Basic artist data - optimized query with minimal joins
# Uses wikibase:label service for efficiency, avoids expensive schema:about lookups

SELECT
  ?artist
  ?artistLabel
  ?artistDescription
  ?isHuman
  ?country ?countryLabel
  ?givenName ?givenNameLabel
  ?familyName ?familyNameLabel
  ?officialWebsite
  ?imageCommons
  ?logoCommons
  ?commonsCategory
  ?formed
  ?disbanded
  ?musicBrainzId
WHERE {
  VALUES ?artist { {{values}} }

  # Check if human (Q5) for person vs group detection - boolean flag avoids row explosion
  BIND(EXISTS { ?artist wdt:P31 wd:Q5 } AS ?isHuman)

  # Country: try origin first (P495), then citizenship (P27)
  OPTIONAL { ?artist wdt:P495 ?country . }
  OPTIONAL {
    FILTER NOT EXISTS { ?artist wdt:P495 ?any }
    ?artist wdt:P27 ?country .
  }

  # Name components for sort name (P735 = given name, P734 = family name)
  OPTIONAL { ?artist wdt:P735 ?givenName . }
  OPTIONAL { ?artist wdt:P734 ?familyName . }

  # Core properties - simple single-value lookups
  OPTIONAL { ?artist wdt:P856 ?officialWebsite . }
  OPTIONAL { ?artist wdt:P18  ?imageCommons . }
  OPTIONAL { ?artist wdt:P154 ?logoCommons . }
  OPTIONAL { ?artist wdt:P373 ?commonsCategory . }
  OPTIONAL { ?artist wdt:P571 ?formed . }
  OPTIONAL { ?artist wdt:P576 ?disbanded . }
  OPTIONAL { ?artist wdt:P434 ?musicBrainzId . }

  # Use wikibase:label service for all labels (efficient, handles fallback)
  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,de,fr,es,it,pt,nl,*" .
  }
}
SPARQL,
            ],
            [
                'name' => 'artist_enrich_genres',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Fetch genre relationships for a batch of artists',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Artist genres - simple query for genre relationships only
# Split from artist_enrich.sparql to reduce query complexity

SELECT ?artist ?genre
WHERE {
  VALUES ?artist { {{values}} }
  ?artist wdt:P136 ?genre .
}
SPARQL,
            ],
            [
                'name' => 'artist_enrich_links',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Fetch social media and streaming platform IDs for artists',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Artist social/streaming links - simple query for external IDs only
# Split from artist_enrich.sparql to reduce query complexity

SELECT
  ?artist
  ?twitter
  ?instagram
  ?facebook
  ?youtubeChannel
  ?spotifyArtistId
  ?appleMusicArtistId
  ?discogsArtistId
  ?deezerArtistId
  ?soundcloudId
  ?bandcampId
  ?subreddit
WHERE {
  VALUES ?artist { {{values}} }

  OPTIONAL { ?artist wdt:P2002 ?twitter . }
  OPTIONAL { ?artist wdt:P2003 ?instagram . }
  OPTIONAL { ?artist wdt:P2013 ?facebook . }
  OPTIONAL { ?artist wdt:P2397 ?youtubeChannel . }
  OPTIONAL { ?artist wdt:P1902 ?spotifyArtistId . }
  OPTIONAL { ?artist wdt:P2850 ?appleMusicArtistId . }
  OPTIONAL { ?artist wdt:P1953 ?discogsArtistId . }
  OPTIONAL { ?artist wdt:P2722 ?deezerArtistId . }
  OPTIONAL { ?artist wdt:P3040 ?soundcloudId . }
  OPTIONAL { ?artist wdt:P3283 ?bandcampId . }
  OPTIONAL { ?artist wdt:P3984 ?subreddit . }
}
SPARQL,
            ],
            [
                'name' => 'artist_name_components',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Lightweight query for given/family name to compute sort_name',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Fetch only name components for sort_name computation
# Lightweight query - only givenName (P735) and familyName (P734)

SELECT ?artist ?givenNameLabel ?familyNameLabel
WHERE {
  VALUES ?artist { {{values}} }

  OPTIONAL { ?artist wdt:P735 ?givenName . }
  OPTIONAL { ?artist wdt:P734 ?familyName . }

  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,de,fr,es,it,pt,nl,*" .
  }
}
SPARQL,
            ],

            // Incremental queries
            [
                'name' => 'incremental/new_genres',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Incremental discovery of NEW genre entities by O-ID cursor',
                'variables' => ['after_filter', 'limit'],
                'query' => <<<'SPARQL'
# Incremental discovery of NEW genre entities by O-ID cursor
# Used by weekly incremental sync to find genres added since last checkpoint

PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT DISTINCT ?genre ?oid WHERE {
  ?genre wdt:P31 wd:Q188451 .

  # Bind numeric O-ID for cursor paging
  BIND(xsd:integer(STRAFTER(STR(?genre), "entity/Q")) AS ?oid)
  {{after_filter}}
}
ORDER BY ?oid
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'incremental/changed_genres_since',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Find genres modified since a timestamp for incremental sync',
                'variables' => ['since', 'after_modified_filter', 'limit'],
                'query' => <<<'SPARQL'
# Incremental discovery of CHANGED genre entities since a timestamp
# Used by weekly incremental sync to find genres modified since last run

PREFIX schema: <http://schema.org/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT DISTINCT ?genre ?modified WHERE {
  ?genre wdt:P31 wd:Q188451 .

  # Get modification timestamp
  ?genre schema:dateModified ?modified .

  # Filter by since timestamp
  FILTER(?modified > "{{since}}"^^xsd:dateTime)
  {{after_modified_filter}}
}
ORDER BY ?modified
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'incremental/genre_enrich',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Enrich a batch of genre QIDs with full metadata',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Enrich a batch of genre QIDs with full metadata
# Used by incremental sync to update changed genres

SELECT DISTINCT
  ?genre
  ?genreLabel
  ?genreDescription
  ?inception
  ?country ?countryLabel
  ?parentGenre ?parentGenreLabel
  ?musicBrainzId
WHERE {
  VALUES ?genre { {{values}} }

  OPTIONAL { ?genre wdt:P571 ?inception . }
  OPTIONAL { ?genre wdt:P495 ?country . }
  OPTIONAL { ?genre wdt:P279 ?parentGenre . }
  OPTIONAL { ?genre wdt:P436 ?musicBrainzId . }

  SERVICE wikibase:label { bd:serviceParam wikibase:language "en". }
}
SPARQL,
            ],
            [
                'name' => 'incremental/new_artists',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Incremental discovery of NEW artist entities by O-ID cursor',
                'variables' => ['after_filter', 'limit'],
                'query' => <<<'SPARQL'
# Incremental discovery of NEW artist entities by O-ID cursor
# Used by weekly incremental sync to find artists added since last checkpoint
# Invalid entities (no labels) are filtered during enrichment step

PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT DISTINCT ?artist ?oid WHERE {
  {
    # Humans who are musicians (broad: includes many solo artists)
    ?artist wdt:P31 wd:Q5 ;
            wdt:P106/wdt:P279* wd:Q639669 .
  }
  UNION
  {
    # Musical groups (including subclasses)
    ?artist wdt:P31/wdt:P279* wd:Q215380 .
  }

  # Bind numeric O-ID for cursor paging
  BIND(xsd:integer(STRAFTER(STR(?artist), "entity/Q")) AS ?oid)
  {{after_filter}}
}
ORDER BY ?oid
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'incremental/changed_artists_since',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Find artists modified since a timestamp for incremental sync',
                'variables' => ['since', 'after_modified_filter', 'limit'],
                'query' => <<<'SPARQL'
# Incremental discovery of CHANGED artist entities since a timestamp
# Used by weekly incremental sync to find artists modified since last run
# Invalid entities (no labels) are filtered during enrichment step

PREFIX schema: <http://schema.org/>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>

SELECT DISTINCT ?artist ?modified WHERE {
  {
    # Humans who are musicians
    ?artist wdt:P31 wd:Q5 ;
            wdt:P106/wdt:P279* wd:Q639669 .
  }
  UNION
  {
    # Musical groups (including subclasses)
    ?artist wdt:P31/wdt:P279* wd:Q215380 .
  }

  # Get modification timestamp
  ?artist schema:dateModified ?modified .

  # Filter by since timestamp
  FILTER(?modified > "{{since}}"^^xsd:dateTime)
  {{after_modified_filter}}
}
ORDER BY ?modified
LIMIT {{limit}}
SPARQL,
            ],
            [
                'name' => 'incremental/albums_for_artists',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Fetch albums for changed artists during incremental sync',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Fetch albums for a batch of artist QIDs - optimized for speed
# Used by weekly incremental sync to refresh albums for changed artists

SELECT DISTINCT
  ?album
  ?albumLabel
  ?albumDescription
  ?artist
  ?albumType
  ?publicationDate
  ?musicBrainzReleaseGroupId
  ?coverImage
WHERE {
  VALUES ?artist { {{values}} }

  ?album wdt:P175 ?artist .

  # Constrain to album-like items (specific types for performance)
  ?album wdt:P31 ?albumType .
  VALUES ?albumType {
    wd:Q482994    # studio album
    wd:Q169930    # EP
    wd:Q134556    # single
    wd:Q222910    # compilation album
    wd:Q209939    # live album
    wd:Q59481898  # remix album
    wd:Q24672043  # soundtrack album
  }

  OPTIONAL { ?album wdt:P577 ?publicationDate . }
  OPTIONAL { ?album wdt:P436 ?musicBrainzReleaseGroupId . }
  OPTIONAL { ?album wdt:P18 ?coverImage . }

  SERVICE wikibase:label {
    bd:serviceParam wikibase:language "en,de,fr,es,it,pt,nl,*" .
  }
}
SPARQL,
            ],
            [
                'name' => 'album_covers',
                'data_source' => 'wikidata',
                'query_type' => 'sparql',
                'description' => 'Fetch cover images for a batch of albums',
                'variables' => ['values'],
                'query' => <<<'SPARQL'
# Fetch cover images for a batch of album QIDs
# Used by WikidataEnrichAlbumCovers job for backfilling

SELECT ?album ?coverImage
WHERE {
  VALUES ?album { {{values}} }
  OPTIONAL { ?album wdt:P18 ?coverImage . }
}
SPARQL,
            ],
        ];
    }
}
