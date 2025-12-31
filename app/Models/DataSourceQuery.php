<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSourceQuery extends Model
{
    protected $fillable = [
        'name',
        'data_source',
        'query_type',
        'query',
        'description',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get a query by name and data source, replacing template variables.
     *
     * @param  string  $name  The query name (e.g., "artist_ids", "incremental/new_genres")
     * @param  string  $dataSource  The data source (e.g., "wikidata", "musicbrainz")
     * @param  array  $vars  Variables to replace in the query ({{key}} => value)
     * @return string The processed query
     *
     * @throws \RuntimeException If query not found or inactive
     */
    public static function get(string $name, string $dataSource = 'wikidata', array $vars = []): string
    {
        $record = static::where('name', $name)
            ->where('data_source', $dataSource)
            ->first();

        if (! $record) {
            throw new \RuntimeException("Query not found: {$name} (data_source: {$dataSource})");
        }

        if (! $record->is_active) {
            throw new \RuntimeException("Query is inactive: {$name} (data_source: {$dataSource})");
        }

        $query = $record->query;

        foreach ($vars as $key => $value) {
            $query = str_replace('{{'.$key.'}}', (string) $value, $query);
        }

        return $query;
    }
}
