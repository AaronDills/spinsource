<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class Sparql
{
    /**
     * Load a SPARQL template from resources/sparql/<name>.sparql and replace {{vars}}.
     */
    public static function load(string $name, array $vars = []): string
    {
        $path = resource_path("sparql/{$name}.sparql");

        if (! File::exists($path)) {
            throw new \RuntimeException("SPARQL file not found: {$path}");
        }

        $query = File::get($path);

        foreach ($vars as $key => $value) {
            $query = str_replace('{{'.$key.'}}', (string) $value, $query);
        }

        return $query;
    }
}
