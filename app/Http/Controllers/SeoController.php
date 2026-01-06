<?php

namespace App\Http\Controllers;

use App\Models\SeoSetting;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Default robots.txt content if not configured in database.
     */
    private const DEFAULT_ROBOTS_TXT = <<<'ROBOTS'
# Spinsearch robots.txt
# https://www.robotstxt.org/robotstxt.html

User-agent: *

# Allow main content
Allow: /
Allow: /search$
Allow: /artist/
Allow: /album/

# Disallow search with query parameters (prevent index bloat)
Disallow: /search?
Disallow: /search-results

# Disallow admin and internal routes
Disallow: /admin/
Disallow: /dashboard
Disallow: /profile
Disallow: /account

# Disallow authentication routes
Disallow: /login
Disallow: /register
Disallow: /password
Disallow: /email
Disallow: /verify-email
Disallow: /confirm-password
Disallow: /forgot-password
Disallow: /reset-password

# Disallow API endpoints
Disallow: /api/
Disallow: /search/autocomplete

# Disallow Laravel internal routes
Disallow: /pulse
Disallow: /logs
Disallow: /telescope
Disallow: /horizon
ROBOTS;

    /**
     * Serve dynamic robots.txt from database with caching.
     */
    public function robots(): Response
    {
        $appUrl = rtrim(config('app.url'), '/');

        // Get robots.txt content from database (cached)
        $robotsContent = SeoSetting::getValue('robots_txt', self::DEFAULT_ROBOTS_TXT);

        // Append sitemap location dynamically
        $content = $robotsContent."\n\n# Sitemap location\nSitemap: {$appUrl}/sitemap.xml\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600', // Browser cache for 1 hour
        ]);
    }
}
