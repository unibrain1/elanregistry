<?php

declare(strict_types=1);

use ElanRegistry\Car\CarRepository;
use ElanRegistry\SitemapService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SitemapService (#1373)
 */
#[Group('fast')]
#[Group('unit')]
final class SitemapServiceTest extends TestCase
{
    private const BASE_URL = 'https://elanregistry.org';

    /**
     * @param list<object{id: int, mtime: string}> $cars
     */
    private function makeService(array $cars = []): SitemapService
    {
        $stubRepo = $this->createStub(CarRepository::class);
        $stubRepo->method('getAllForSitemap')->willReturn($cars);

        return new SitemapService($stubRepo);
    }

    public function testBuildXmlProducesWellFormedXmlDocument(): void
    {
        $service = $this->makeService();
        $xml     = $service->buildXml(self::BASE_URL);

        $this->assertStringStartsWith('<?xml version="1.0"', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'Sitemap output must be well-formed XML');
    }

    public function testBuildXmlIncludesAllStaticPages(): void
    {
        $service = $this->makeService();
        $xml     = $service->buildXml(self::BASE_URL);

        $this->assertStringContainsString(
            '<loc>' . self::BASE_URL . '/app/owner/cars/index.php</loc>',
            $xml,
        );
        $this->assertStringContainsString(
            '<loc>' . self::BASE_URL . '/docs/guides/index.php</loc>',
            $xml,
        );
        $this->assertStringContainsString(
            '<loc>' . self::BASE_URL . '/app/owner/reports/statistics.php</loc>',
            $xml,
        );

        $sitemap = simplexml_load_string($xml);
        $this->assertNotFalse($sitemap);
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = $sitemap->xpath('//s:url');
        // 6 static pages + 0 cars in this scenario
        $this->assertCount(6, $urls);
    }

    public function testBuildXmlIncludesOneUrlPerCar(): void
    {
        $cars = [
            (object) ['id' => 101, 'mtime' => '2026-03-15 10:30:00'],
            (object) ['id' => 202, 'mtime' => '2025-12-01 00:00:00'],
            (object) ['id' => 303, 'mtime' => '2024-07-04 23:59:59'],
        ];
        $service = $this->makeService($cars);
        $xml     = $service->buildXml(self::BASE_URL);

        foreach ($cars as $car) {
            $this->assertStringContainsString(
                '<loc>' . self::BASE_URL . '/app/owner/cars/details.php?car_id=' . $car->id . '</loc>',
                $xml,
            );
        }

        $this->assertStringContainsString('<lastmod>2026-03-15</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2025-12-01</lastmod>', $xml);
        $this->assertStringContainsString('<lastmod>2024-07-04</lastmod>', $xml);

        $sitemap = simplexml_load_string($xml);
        $this->assertNotFalse($sitemap);
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = $sitemap->xpath('//s:url');
        // 6 static pages + 3 cars
        $this->assertCount(9, $urls);
    }

    public function testBuildXmlExcludesFactoryAndPrivacyPages(): void
    {
        $cars = [
            (object) ['id' => 1, 'mtime' => '2026-01-01 00:00:00'],
        ];
        $service = $this->makeService($cars);
        $xml     = $service->buildXml(self::BASE_URL);

        $this->assertStringNotContainsString('factory.php', $xml);
        $this->assertStringNotContainsString('privacy.php', $xml);
    }

    public function testBuildXmlWithNoCarsProducesOnlyStaticPages(): void
    {
        $service = $this->makeService([]);
        $xml     = $service->buildXml(self::BASE_URL);

        $sitemap = simplexml_load_string($xml);
        $this->assertNotFalse($sitemap, 'Sitemap output must be well-formed XML even with zero cars');
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = $sitemap->xpath('//s:url');

        $this->assertCount(6, $urls);
        $this->assertStringNotContainsString('details.php?car_id=', $xml);
    }

    public function testCarEntryHasMonthlyChangefreqAndPointEightPriority(): void
    {
        $cars = [
            (object) ['id' => 42, 'mtime' => '2026-05-20 12:00:00'],
        ];
        $service = $this->makeService($cars);
        $xml     = $service->buildXml(self::BASE_URL);

        $sitemap = simplexml_load_string($xml);
        $this->assertNotFalse($sitemap);
        $sitemap->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $carUrls = $sitemap->xpath('//s:url[s:loc="' . self::BASE_URL . '/app/owner/cars/details.php?car_id=42"]');

        $this->assertCount(1, $carUrls);
        $carUrl = $carUrls[0];
        $this->assertSame('monthly', (string) $carUrl->changefreq);
        $this->assertSame('0.8', (string) $carUrl->priority);
    }
}
