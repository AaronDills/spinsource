<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Artist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use XMLWriter;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:generate-sitemap
                            {--chunk=1000 : Number of records to process at a time}
                            {--max-urls=50000 : Maximum URLs per sitemap file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sitemap.xml (with index) for SEO';

    private string $appUrl;

    private int $maxUrls;

    private int $currentSitemapIndex = 0;

    private int $currentUrlCount = 0;

    private ?XMLWriter $currentWriter = null;

    private array $sitemapFiles = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating sitemaps...');

        $this->appUrl = rtrim(config('app.url'), '/');
        $this->maxUrls = (int) $this->option('max-urls');
        $chunkSize = (int) $this->option('chunk');

        // Clean up old sitemap files
        $this->cleanupOldSitemaps();

        // Start first sitemap
        $this->startNewSitemap();

        // Static pages
        $this->addUrl($this->appUrl.'/', now()->format('Y-m-d'), 'daily', '1.0');
        $this->addUrl($this->appUrl.'/search', now()->format('Y-m-d'), 'daily', '0.9');

        // Artist pages
        $artistCount = 0;
        Artist::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($artists) use (&$artistCount) {
                foreach ($artists as $artist) {
                    $lastmod = $artist->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d');
                    $this->addUrl(route('artist.show', $artist->id), $lastmod, 'weekly', '0.8');
                    $artistCount++;
                }
            });

        $this->info("Added {$artistCount} artist pages");

        // Album pages
        $albumCount = 0;
        Album::query()
            ->select(['id', 'updated_at'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($albums) use (&$albumCount) {
                foreach ($albums as $album) {
                    $lastmod = $album->updated_at?->format('Y-m-d') ?? now()->format('Y-m-d');
                    $this->addUrl(route('album.show', $album->id), $lastmod, 'weekly', '0.7');
                    $albumCount++;
                }
            });

        $this->info("Added {$albumCount} album pages");

        // Close current sitemap
        $this->closeSitemap();

        // Generate sitemap index
        $this->generateSitemapIndex();

        $totalUrls = 2 + $artistCount + $albumCount;
        $this->info('Generated '.count($this->sitemapFiles).' sitemap files');
        $this->info("Total URLs: {$totalUrls}");
        $this->info('Sitemap index: '.public_path('sitemap.xml'));

        return Command::SUCCESS;
    }

    /**
     * Clean up old sitemap files.
     */
    private function cleanupOldSitemaps(): void
    {
        $files = glob(public_path('sitemap*.xml'));
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Start a new sitemap file.
     */
    private function startNewSitemap(): void
    {
        $this->currentSitemapIndex++;
        $this->currentUrlCount = 0;

        $filename = "sitemap-{$this->currentSitemapIndex}.xml";
        $filepath = public_path($filename);

        $this->currentWriter = new XMLWriter;
        $this->currentWriter->openUri($filepath);
        $this->currentWriter->setIndent(true);
        $this->currentWriter->setIndentString('  ');

        $this->currentWriter->startDocument('1.0', 'UTF-8');
        $this->currentWriter->startElement('urlset');
        $this->currentWriter->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $this->sitemapFiles[] = $filename;
    }

    /**
     * Close the current sitemap file.
     */
    private function closeSitemap(): void
    {
        if ($this->currentWriter) {
            $this->currentWriter->endElement(); // urlset
            $this->currentWriter->endDocument();
            $this->currentWriter->flush();
            $this->currentWriter = null;
        }
    }

    /**
     * Add a URL to the current sitemap, rotating to a new file if needed.
     */
    private function addUrl(string $loc, string $lastmod, string $changefreq, string $priority): void
    {
        // Check if we need to start a new sitemap
        if ($this->currentUrlCount >= $this->maxUrls) {
            $this->closeSitemap();
            $this->startNewSitemap();
        }

        $this->currentWriter->startElement('url');
        $this->currentWriter->writeElement('loc', $loc);
        $this->currentWriter->writeElement('lastmod', $lastmod);
        $this->currentWriter->writeElement('changefreq', $changefreq);
        $this->currentWriter->writeElement('priority', $priority);
        $this->currentWriter->endElement();

        // Flush periodically to avoid memory buildup
        if ($this->currentUrlCount % 1000 === 0) {
            $this->currentWriter->flush();
        }

        $this->currentUrlCount++;
    }

    /**
     * Generate the sitemap index file.
     */
    private function generateSitemapIndex(): void
    {
        $indexPath = public_path('sitemap.xml');
        $lastmod = now()->format('Y-m-d');

        $xml = new XMLWriter;
        $xml->openUri($indexPath);
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($this->sitemapFiles as $filename) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $this->appUrl.'/'.$filename);
            $xml->writeElement('lastmod', $lastmod);
            $xml->endElement();
        }

        $xml->endElement(); // sitemapindex
        $xml->endDocument();
        $xml->flush();
    }
}
