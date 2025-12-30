# SpinSource

A Laravel application for aggregating and managing music metadata from Wikidata. SpinSource fetches artist, album, and genre information via SPARQL queries and stores it in a relational database for use in music discovery and rating applications.

## Architecture Overview

### Core Components

**Data Layer**
- **Eloquent Models**: `Artist`, `Album`, `Genre`, `Country`, `ArtistLink`, `UserAlbumRating`
- **Relationships**: Artists belong to countries and have many albums, links, and genres (via pivot table)
- **Migrations**: Located in `database/migrations/` for all core tables and pivot relationships

**Wikidata Integration**
- **SPARQL Templates**: Parameterized query templates in `resources/sparql/` (`genres.sparql`, `artist_ids.sparql`, `artist_enrich.sparql`)
- **Template Loader**: `App\Support\Sparql` handles loading and variable substitution in SPARQL templates
- **Configuration**: `config/wikidata.php` stores endpoint URL and user agent settings

**Job-Based Data Seeding**
- **Queue Jobs**: `WikidataSeedGenres`, `WikidataSeedArtistIds`, `WikidataEnrichArtists` handle paginated data fetching
- **Self-Dispatching**: Jobs automatically dispatch the next page when results indicate more data exists
- **Retry Logic**: Built-in exponential backoff for handling Wikidata query service rate limits
- **Laravel Horizon**: Manages queue workers for background job processing

**Console Commands**
- `php artisan wikidata:dispatch-seed-genres` – Dispatches genre seeding jobs
- `php artisan wikidata:dispatch-seed-artists` – Dispatches artist seeding jobs
- `php artisan wikidata:sync` – Orchestrates sync operations

### Data Flow

```
Wikidata SPARQL Endpoint
         ↓
   SPARQL Templates (resources/sparql/)
         ↓
   Queue Jobs (app/Jobs/Wikidata*.php)
         ↓
   Eloquent Models → MySQL/PostgreSQL
```

## Installation

1. Clone the repository and install dependencies:
```bash
git clone <repository-url> spinsource
cd spinsource
composer install
```

2. Configure environment variables:
```env
WIKIDATA_USER_AGENT="SpinSource/1.0 (you@example.com)"
WIKIDATA_SPARQL_ENDPOINT="https://query.wikidata.org/sparql"
```

3. Run migrations:
```bash
php artisan migrate
```

4. Start Horizon for queue processing:
```bash
php artisan horizon
```

5. Seed data from Wikidata:
```bash
php artisan wikidata:dispatch-seed-genres
php artisan wikidata:dispatch-seed-artists
```

## Scheduling

The scheduler is configured in `routes/console.php`:

| Command | Schedule | Description |
|---------|----------|-------------|
| `wikidata:dispatch-seed-genres` | 3:00 AM | Seed genres from Wikidata |
| `wikidata:dispatch-seed-artists` | 3:30 AM | Seed artists from Wikidata |
| `wikidata:sync` | 8:00 PM | Orchestrate sync operations |

To run the scheduler, add this cron entry:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Search

SpinSource uses [Typesense](https://typesense.org/) via [Laravel Scout](https://laravel.com/docs/scout) for full-text search.

### Searchable Models

| Model | Indexed Fields | Query Fields |
|-------|----------------|--------------|
| `Artist` | `id`, `name`, `sort_name`, `description` | `name`, `sort_name`, `description` |
| `Album` | `id`, `title`, `description`, `release_year`, `artist_name` | `title`, `artist_name`, `description` |

### Configuration

Add these environment variables:

```env
SCOUT_DRIVER=typesense
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_API_KEY=your-api-key
```

When using Docker, the app container connects to `typesense` as the host (configured in `docker-compose.yml`).

### Syncing Indexes

Import all records to Typesense:

```bash
php artisan scout:import "App\Models\Artist"
php artisan scout:import "App\Models\Album"
```

Flush and rebuild indexes:

```bash
php artisan scout:flush "App\Models\Artist"
php artisan scout:flush "App\Models\Album"
php artisan scout:import "App\Models\Artist"
php artisan scout:import "App\Models\Album"
```

## Roadmap

- Album seeding from Wikidata
- External link policy enforcement (allowlisted platforms, subreddit-only Reddit links)
