# Spin Source – Custom Project Files

This folder contains **only** the custom implementation we designed together, arranged in a Laravel project structure.

## What’s included
- Wikidata config: `config/wikidata.php`
- SPARQL templates: `resources/sparql/`
- SPARQL loader: `app/Support/Sparql.php`
- Seed commands:
  - `php artisan wikidata:seed-genres`
  - `php artisan wikidata:sync`
- Eloquent models for:
  - Country, Genre, Artist, Album, ArtistLink, UserAlbumRating
- Migrations for core tables and pivots

## How to use
1) Create a fresh Laravel app:
```bash
composer create-project laravel/laravel spinsource
cd spinsource
```

2) Copy these files into your Laravel app root (merge directories).

3) Add env vars:
```env
WIKIDATA_USER_AGENT="SpinSource/1.0 (you@example.com)"
WIKIDATA_SPARQL_ENDPOINT="https://query.wikidata.org/sparql"
```

4) Run migrations:
```bash
php artisan migrate
```

5) Dry-run seeding:
```bash
php artisan wikidata:seed-genres --limit=25 --dry-run
```

6) Seed:
```bash
php artisan wikidata:seed-genres
php artisan wikidata:sync
```

## Scheduling (nightly)
Add to `app/Console/Kernel.php`:

```php
protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('wikidata:sync')
        ->dailyAt('02:00')
        ->onOneServer()
        ->withoutOverlapping();
}
```

## Notes / Next steps
- `wikidata:sync` currently runs only the genre seeder.
- Next commands to implement:
  - `wikidata:seed-artists` (with websites, social allowlist, image/logo commons files)
  - `wikidata:seed-albums`
- External link policy is enforced in the upcoming artist seeder (allowlisted platforms, subreddit-only Reddit).
