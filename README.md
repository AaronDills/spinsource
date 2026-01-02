# SpinSource

A Laravel application for aggregating and managing music metadata from multiple sources including Wikidata and MusicBrainz. SpinSource builds a comprehensive music database with artists, albums, tracks, and genres, powering a full-text search experience for music discovery.

## Current Scale

| Entity   | Count    |
|----------|----------|
| Artists  | 330,000+ |
| Albums   | 324,000+ |
| Genres   | 6,200+   |
| Tracks   | Growing  |

## Features

- **Multi-Source Data Aggregation**: Artists, albums, and genres sourced from Wikidata; tracklists from MusicBrainz
- **Canonical ID Mapping**: Cross-references Wikidata QIDs, MusicBrainz MBIDs, Spotify IDs, Apple Music IDs, and Discogs IDs
- **Smart Artist Sorting**: Natural sort names ("Lennon, John" for people, "rolling stones" for bands)
- **Full-Text Search**: Typesense-powered instant search across artists, albums, and genres with ranking
- **Rate-Limited Processing**: Dedicated queues respect API limits for Wikidata and MusicBrainz
- **Incremental Sync**: Weekly updates fetch only new and changed entities
- **Provenance Tracking**: Every record tracks its source and last sync timestamp

## Architecture

### Data Models

| Model | Description | External IDs |
|-------|-------------|--------------|
| **Artist** | Musicians and bands with metadata, country, and genre associations | Wikidata QID, MusicBrainz MBID, Spotify ID, Apple Music ID, Discogs ID |
| **Album** | Releases linked to artists with type classification | Wikidata QID, MusicBrainz Release Group/Release MBIDs, Spotify ID, Apple Music ID |
| **Track** | Individual songs on albums with position and duration | MusicBrainz Recording ID |
| **Genre** | Hierarchical music genres with parent relationships | Wikidata QID |
| **Country** | Geographic origins for artists | Wikidata QID |

### Web Routes

| Route | Description |
|-------|-------------|
| `/` | Home page with search |
| `/search?q=` | AJAX search endpoint (returns JSON) |
| `/search-results?q=` | Full search results page |
| `/artist/{id}` | Artist detail page |
| `/album/{id}` | Album detail page with tracklist |
| `/horizon` | Queue monitoring dashboard |

### Data Sources

#### Wikidata (Primary)
- Artists, albums, genres via SPARQL queries
- Album cover images from Wikimedia Commons
- Wikipedia URLs and descriptions
- External ID cross-references (Spotify, Apple Music, Discogs)

#### MusicBrainz (Secondary)
- Album tracklists with accurate track positions
- Recording durations
- Release disambiguation (selects best edition for tracklist)

### Queue Architecture

Horizon manages multiple supervisor groups with rate-limited processing:

| Supervisor | Queue | Workers | Purpose |
|------------|-------|---------|---------|
| `supervisor-1` | `default` | 3-10 | General application jobs |
| `wikidata-supervisor` | `wikidata` | 1 | Wikidata API calls (rate-limited) |
| `musicbrainz-supervisor` | `musicbrainz` | 1 | MusicBrainz API calls (~50 req/min) |

### Job Types

**Backfill Jobs** (initial import or recovery):
- `WikidataSeedGenres` - Fetch all music genres
- `WikidataSeedArtistIds` - Discover artists with MusicBrainz IDs
- `WikidataEnrichArtists` - Populate artist details (3 parallel queries)
- `WikidataSeedAlbums` - Fetch albums for all artists
- `WikidataEnrichAlbumCovers` - Fetch cover images from Commons
- `MusicBrainzSeedTracklists` - Fetch tracklists for albums

**Incremental Jobs** (weekly sync):
- `DiscoverNewGenres` / `DiscoverChangedGenres` - Genre updates
- `DiscoverNewArtistIds` / `DiscoverChangedArtists` - Artist updates
- `RefreshAlbumsForChangedArtists` - Album updates for modified artists

### Artisan Commands

```bash
# Backfill commands (run in order for initial import)
php artisan wikidata:dispatch-seed-genres
php artisan wikidata:dispatch-seed-artists
php artisan wikidata:dispatch-seed-albums
php artisan wikidata:dispatch-enrich-album-covers

# Maintenance commands
php artisan wikidata:recompute-sort-names    # Recalculate artist sort names
php artisan artists:recompute-metrics        # Recalculate album/link counts
php artisan musicbrainz:reselect-release     # Re-select best release for tracklist
```

## Installation

### Docker (Recommended)

```bash
# Clone and start
git clone <repository-url> spinsource
cd spinsource
cp .env.example .env

# Start all services
docker compose up -d

# Run migrations
docker compose exec app php artisan migrate

# Generate app key
docker compose exec app php artisan key:generate
```

### Manual Installation

```bash
# Install dependencies
composer install
npm install && npm run build

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start queue workers
php artisan horizon
```

## Configuration

### Environment Variables

```env
# Application
APP_NAME=SpinSource
APP_ENV=local
APP_DEBUG=true

# Database
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=spinsource

# Redis (for queues and cache)
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Wikidata
WIKIDATA_USER_AGENT="SpinSource/1.0 (your@email.com)"
WIKIDATA_SPARQL_ENDPOINT="https://query.wikidata.org/sparql"

# Search (Typesense)
SCOUT_DRIVER=typesense
TYPESENSE_HOST=typesense
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=your-api-key
```

## Seeding Data

For initial data population, run these commands in order:

```bash
# 1. Seed genres first (standalone reference data)
php artisan wikidata:dispatch-seed-genres

# 2. Wait for genres to complete, then seed artists
php artisan wikidata:dispatch-seed-artists

# 3. Wait for artists to complete, then seed albums
php artisan wikidata:dispatch-seed-albums

# 4. Optionally fetch album covers
php artisan wikidata:dispatch-enrich-album-covers
```

Monitor progress via Horizon dashboard at `/horizon`.

## Scheduling

The scheduler runs weekly incremental syncs automatically. Add this cron entry:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Scheduled Tasks

| Schedule | Task | Description |
|----------|------|-------------|
| Sunday 2:00 AM | Wikidata Weekly Sync | Discover new/changed genres, artists, albums |
| Daily 3:00 AM | MusicBrainz Tracklist Sync | Fetch tracklists for albums missing them |
| Sunday 6:00 AM | Search Index Rebuild | Rebuild Typesense indexes |

## Search

SpinSource uses Typesense via Laravel Scout for full-text search with ranking.

### Indexed Models

| Model | Searchable Fields | Ranking Factors |
|-------|-------------------|-----------------|
| `Artist` | name, sort_name | Wikipedia presence, album count, Spotify presence |
| `Album` | title, artist_name | - |
| `Genre` | name, description | - |

### Rebuilding Indexes

```bash
php artisan scout:flush "App\Models\Artist"
php artisan scout:flush "App\Models\Album"
php artisan scout:flush "App\Models\Genre"
php artisan scout:import "App\Models\Artist"
php artisan scout:import "App\Models\Album"
php artisan scout:import "App\Models\Genre"
```

## Development

### Docker Services

| Service | Port | Description |
|---------|------|-------------|
| app | 80, 5173 | Laravel application + Vite dev server |
| db | 3306 | MySQL 8.0 |
| redis | 6379 | Redis for cache/queues |
| typesense | 8108 | Search engine |
| mailpit | 8025, 1025 | Email testing (Web UI + SMTP) |

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

Extend the base class to get queue routing and rate limiting:

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

### Adding New MusicBrainz Jobs

```php
class MyMusicBrainzJob extends MusicBrainzJob
{
    public function handle(): void
    {
        // Automatically uses musicbrainz queue with rate limiting
    }
}
```

## Tech Stack

- **Framework**: Laravel 12
- **Database**: MySQL 8.0
- **Cache/Queue**: Redis 7
- **Search**: Typesense 27
- **Queue Management**: Laravel Horizon
- **Frontend**: Blade + Alpine.js + Tailwind CSS
- **Build Tool**: Vite

## License

[Your license here]
