<?php

return [
    'endpoint' => env('WIKIDATA_SPARQL_ENDPOINT', 'https://query.wikidata.org/sparql'),
    // Use a descriptive UA per Wikidata Query Service etiquette
    'user_agent' => env('WIKIDATA_USER_AGENT', 'SpinSource/1.0 (contact@example.com)'),
];
