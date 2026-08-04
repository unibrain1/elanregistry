<?php

declare(strict_types=1);

namespace ElanRegistry;

use ElanRegistry\Car\CarRepository;

/**
 * Builds the public sitemap.xml body for elanregistry.org (#1373).
 */
class SitemapService
{
    /** @var list<array{path: string, changefreq: string, priority: string}> */
    private const STATIC_PAGES = [
        ['path' => '/app/owner/cars/index.php', 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['path' => '/app/owner/reports/statistics.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => '/docs/reference/paint-colors.php', 'changefreq' => 'yearly', 'priority' => '0.7'],
        ['path' => '/docs/reference/index.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
        ['path' => '/docs/reference/identification-guide.php', 'changefreq' => 'yearly', 'priority' => '0.6'],
        ['path' => '/docs/guides/index.php', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ];

    public function __construct(private CarRepository $carRepository) {}

    /**
     * @param string $baseUrl Origin to prefix every URL with (e.g. https://elanregistry.org)
     * @return string Well-formed sitemap.xml document body
     */
    public function buildXml(string $baseUrl): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach (self::STATIC_PAGES as $page) {
            $this->writeUrl($xml, $baseUrl . $page['path'], $page['changefreq'], $page['priority']);
        }

        foreach ($this->carRepository->getAllForSitemap() as $car) {
            $this->writeUrl(
                $xml,
                $baseUrl . '/app/owner/cars/details.php?car_id=' . $car->id,
                'monthly',
                '0.8',
                $car->mtime,
            );
        }

        $xml->endElement();
        $xml->endDocument();
        return $xml->outputMemory();
    }

    private function writeUrl(\XMLWriter $xml, string $loc, string $changefreq, string $priority, ?string $lastmod = null): void
    {
        $xml->startElement('url');
        $xml->writeElement('loc', $loc);
        if ($lastmod !== null) {
            $xml->writeElement('lastmod', date('Y-m-d', strtotime($lastmod)));
        }
        $xml->writeElement('changefreq', $changefreq);
        $xml->writeElement('priority', $priority);
        $xml->endElement();
    }
}
