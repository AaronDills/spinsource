# SpinSource

A Laravel application for aggregating and managing music metadata from Wikidata. SpinSource fetches artist, album, and genre information via SPARQL queries and stores it in a relational database for music discovery and rating applications.

## Features

- **Full Wikidata Integration**: Artists, albums, and genres sourced from Wikidata with MusicBrainz IDs
- **Smart Sorting**: Artists sort naturally ("Lennon, John" for people, "rolling stones" for bands)
- **Rate-Limited Processing**: Dedicated queue respects Wikidata's API limits
- **Incremental Sync**: Weekly updates fetch only new and changed entities
- **Full-Text Search**: Typesense-powered search across artists and albums

## Architecture

### Data Models

- **Artist**: Musicians and bands with country, genre associations, and social links
- **Album**: Releases linked to artists with type classification (album, EP, single, etc.)
- **Genre**: Hierarchical music genres with parent relationships
- **Country**: Geographic origins for artists and genres

### Wikidata Jobs

All Wikidata jobs extend a common `WikidataJob` base class that provides:
- Automatic queue routing to the rate-limited `wikidata` queue
- Retry logic with exponential backoff
- Helper methods for parsing Wikidata responses

**Seeding Jobs** (full data import):
- `WikidataSeedGenres` - Fetch all music genres
- `WikidataSeedArtistIds` - Discover artists with MusicBrainz IDs
- `WikidataEnrichArtists` - Populate artist details (split into 3 queries to avoid timeouts)
- `WikidataSeedAlbums` - Fetch albums for all artists

**Incremental Jobs** (weekly updates):
- `DiscoverNewGenres` / `DiscoverNewArtistIds` - Find newly added entities
- `DiscoverChangedArtists` / `DiscoverChangedGenres` - Find modified entities
- `RefreshAlbumsForChangedArtists` - Update albums when artists change

### Queue Architecture

Horizon manages two supervisor groups:

| Supervisor | Queue | Workers | Purpose |
|------------|-------|---------|---------|
| `supervisor-1` | `default` | 3-10 | General application jobs |
| `wikidata-supervisor` | `wikidata` | 1 | Wikidata API calls (rate-limited) |

The single wikidata worker prevents 429 errors from Wikidata's query service.

## Installation

1. Clone and install dependencies:
```bash
git clone <repository-url> spinsource
cd spinsource
composer install
npm install && npm run build
```

2. Configure environment:
```env
# Wikidata
WIKIDATA_USER_AGENT="SpinSource/1.0 (your@email.com)"
WIKIDATA_SPARQL_ENDPOINT="https://query.wikidata.org/sparql"

# Search (Typesense)
SCOUT_DRIVER=typesense
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_API_KEY=your-api-key
```

3. Run migrations:
```bash
php artisan migrate
```

4. Start Horizon:
```bash
php artisan horizon
```

## Seeding Data

For initial data population, run these commands in order (genres must exist before artists):

```bash
# 1. Seed genres first
php artisan wikidata:dispatch-seed-genres

# 2. Wait for genres to complete, then seed artists
php artisan wikidata:dispatch-seed-artists

# 3. Wait for artists to complete, then seed albums
php artisan wikidata:dispatch-seed-albums
```

Monitor progress via Horizon dashboard at `/horizon` or:
```bash
php artisan queue:monitor wikidata
```

## Scheduling

Weekly incremental sync runs automatically. Add this cron entry:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler handles:
- Weekly discovery of new/changed genres and artists
- Album refresh for modified artists
- Search index rebuilding

## Search

SpinSource uses Typesense via Laravel Scout for full-text search.

### Indexed Models

| Model | Searchable Fields |
|-------|-------------------|
| `Artist` | name, sort_name, description |
| `Album` | title, artist_name, description |

### Rebuilding Indexes

```bash
php artisan scout:flush "App\Models\Artist"
php artisan scout:flush "App\Models\Album"
php artisan scout:import "App\Models\Artist"
php artisan scout:import "App\Models\Album"
```

## Development

### SPARQL Templates

Query templates live in `resources/sparql/`. Variables use `{{variable}}` syntax:

```sparql
SELECT ?artist WHERE {
  VALUES ?artist { {{values}} }
  ...
}
LIMIT {{limit}}
```

Load templates via the `Sparql` helper:
```php
$sparql = Sparql::load('artist_enrich_basic', ['values' => $values]);
```

### Adding New Wikidata Jobs

Extend the base class to get queue routing and rate limiting for free:

```php
class MyWikidataJob extends WikidataJob
{
    public function __construct(public array $qids = [])
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $response = $this->executeWdqsRequest($sparql);
        // Process response...
    }
}
```

## Docker

The project includes Docker Compose for local development:

```bash
docker compose up -d
docker exec spinsource-app php artisan migrate
docker exec spinsource-app php artisan horizon
```
