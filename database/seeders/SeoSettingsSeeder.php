<?php

namespace Database\Seeders;

use App\Models\SeoSetting;
use Illuminate\Database\Seeder;

class SeoSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $robotsTxt = <<<'ROBOTS'
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

        SeoSetting::setValue(
            'robots_txt',
            $robotsTxt,
            'Content for robots.txt file (without sitemap line, which is appended dynamically)'
        );

        $this->command->info('SEO settings seeded successfully.');
    }
}
