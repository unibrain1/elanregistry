<?php

declare(strict_types=1);

use ElanRegistry\CarView;
use ElanRegistry\Exceptions\ImageProcessingException;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * CarView class tests
 *
 * Tests static utility methods for car display and image generation.
 * Focus: Input validation, exception handling, HTML structure.
 */
#[Group('fast')]
final class CarViewTest extends TestCase
{
    // ============================================================
    // loadPicture() TESTS
    // ============================================================

    public function testLoadPictureThrowsExceptionForEmptyArray(): void
    {
        $this->expectException(ImageProcessingException::class);
        $this->expectExceptionMessage('Invalid image data provided');

        CarView::loadPicture([]);
    }

    public function testLoadPictureThrowsExceptionForMissingPath(): void
    {
        $this->expectException(ImageProcessingException::class);

        CarView::loadPicture(['name' => 'test.jpg']);
    }

    public function testLoadPictureThrowsExceptionForEmptyPath(): void
    {
        $this->expectException(ImageProcessingException::class);

        CarView::loadPicture(['path' => '']);
    }

    public function testLoadPictureReturnsThumbnailHtml(): void
    {
        $image = ['path' => '/userimages/cars/test-image.jpg'];

        $html = CarView::loadPicture($image, true);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('width="100"', $html);
        $this->assertStringContainsString('height="75"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('-resized-100.jpg', $html);
    }

    public function testLoadPictureReturnsFullSizeHtml(): void
    {
        $image = ['path' => '/userimages/cars/test-image.jpg'];

        $html = CarView::loadPicture($image, false);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('srcset="', $html);
        $this->assertStringContainsString('sizes="50vw"', $html);
    }

    public function testLoadPictureGeneratesResponsiveSrcset(): void
    {
        $image = ['path' => '/userimages/cars/test-image.jpg'];

        $html = CarView::loadPicture($image, false);

        // Verify all responsive sizes are present
        $this->assertStringContainsString('-resized-100.jpg 100w', $html);
        $this->assertStringContainsString('-resized-300.jpg 300w', $html);
        $this->assertStringContainsString('-resized-768.jpg 768w', $html);
        $this->assertStringContainsString('-resized-1024.jpg 1024w', $html);
    }

    public function testLoadPicturePrimaryImageDoesNotLazyLoad(): void
    {
        $image = ['path' => '/userimages/cars/test-image.jpg'];

        $html = CarView::loadPicture($image, false, true);

        // Primary image should NOT have loading="lazy"
        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    public function testLoadPictureNonPrimaryImageLazyLoads(): void
    {
        $image = ['path' => '/userimages/cars/test-image.jpg'];

        $html = CarView::loadPicture($image, false, false);

        $this->assertStringContainsString('loading="lazy"', $html);
    }

    public function testLoadPicturePreservesPathStructure(): void
    {
        $image = ['path' => '/userimages/cars/subdir/my-car.png'];

        $html = CarView::loadPicture($image, true);

        $this->assertStringContainsString('/userimages/cars/subdir/', $html);
        $this->assertStringContainsString('my-car-resized-100.png', $html);
    }

    // ============================================================
    // displayCarousel() TESTS
    // ============================================================

    public function testDisplayCarouselShowsPlaceholderForNoImages(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([]);

        $html = CarView::displayCarousel($car);

        $this->assertStringContainsString('No Photos Available', $html);
        $this->assertStringContainsString('fa-camera', $html);
        $this->assertStringContainsString('photo-placeholder', $html);
    }

    public function testDisplayCarouselShowsSingleImageWithoutControls(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([
            ['path' => '/userimages/cars/test.jpg'],
        ]);

        $html = CarView::displayCarousel($car);

        // Should contain image but no carousel controls for single image
        $this->assertStringContainsString('<img', $html);
        // Carousel wrapper structure present
        $this->assertStringContainsString('<!--Start displayCarousel -->', $html);
        $this->assertStringContainsString('<!--End displayCarousel -->', $html);
    }

    public function testDisplayCarouselContainsCarouselStructure(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([
            ['path' => '/userimages/cars/test.jpg'],
        ]);

        $html = CarView::displayCarousel($car);

        $this->assertStringContainsString('displayCarousel', $html);
    }

    public function testDisplayCarouselWithInstanceIdShowsPlaceholderForNoImages(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([]);

        $html = CarView::displayCarousel($car, 42);

        $this->assertStringContainsString('No Photos Available', $html);
    }

    public function testDisplayCarouselWithSameInstanceIdProducesDeterministicOutput(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([]);

        $html1 = CarView::displayCarousel($car, 99);
        $html2 = CarView::displayCarousel($car, 99);

        $this->assertSame($html1, $html2);
    }

    public function testDisplayCarouselInstanceIdAppearsInMultiImageCarouselDomIds(): void
    {
        $car = $this->createStub(\ElanRegistry\Car\Car::class);
        $car->method('images')->willReturn([
            ['path' => '/userimages/cars/img1.jpg'],
            ['path' => '/userimages/cars/img2.jpg'],
        ]);

        $html = CarView::displayCarousel($car, 42);

        $this->assertStringContainsString('id="myCarousel-42"', $html);
        $this->assertStringContainsString('id="carousel-selector-42-0"', $html);
        $this->assertStringContainsString('id="carousel-selector-42-1"', $html);
        $this->assertStringContainsString('data-bs-target="#myCarousel-42"', $html);
    }

    public function testLoadPictureEscapesSpecialCharsInPath(): void
    {
        $image = ['path' => '/userimages/cars/my-"test"<car>.jpg'];

        $html = CarView::loadPicture($image, true);

        $this->assertStringNotContainsString('"test"', $html);
        $this->assertStringContainsString('&quot;test&quot;', $html);
    }

    // ============================================================
    // buildCarSchema() TESTS (Issue #1371)
    // ============================================================

    private function makeCarData(array $overrides = []): object
    {
        return (object)array_merge([
            'year' => 1969,
            'series' => 'S4',
            'variant' => 'Roadster',
            'chassis' => '1234B',
            'color' => 'Racing Green',
        ], $overrides);
    }

    public function testBuildCarSchemaBasicFields(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(), 'https://elanregistry.org/app/owner/cars/details.php?car_id=1');

        $this->assertSame('https://schema.org', $schema['@context']);
        $this->assertSame('Car', $schema['@type']);
        $this->assertSame('1969 Lotus Elan S4 (Roadster)', $schema['name']);
        $this->assertSame('1234B', $schema['vehicleIdentificationNumber']);
        $this->assertSame('1969', $schema['vehicleModelDate']);
        $this->assertSame('Elan', $schema['model']);
        $this->assertSame('https://elanregistry.org/app/owner/cars/details.php?car_id=1', $schema['url']);
        $this->assertSame('Racing Green', $schema['color']);
        $this->assertSame('Roadster', $schema['bodyType']);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
    }

    /**
     * Regression test: series values starting with "+2" (+2, +2S, +2S/130, +2S/130/5 —
     * confirmed against the live cars table) denote the Lotus Elan Plus 2, a distinct
     * model line. An earlier implementation hardcoded "Elan" for name/model regardless
     * of series, mislabeling ~470 Plus 2 cars in the published structured data.
     */
    public function testBuildCarSchemaPlus2SeriesUsesElanPlus2Model(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['series' => '+2', 'variant' => 'FHC']), '');

        $this->assertSame('Elan Plus 2', $schema['model']);
        $this->assertSame('1969 Lotus Elan Plus 2 (FHC)', $schema['name']);
    }

    public function testBuildCarSchemaPlus2SubSeriesStripsPlus2PrefixFromNameSeriesPortion(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['series' => '+2S/130', 'variant' => 'Roadster']), '');

        $this->assertSame('Elan Plus 2', $schema['model']);
        $this->assertSame('1969 Lotus Elan Plus 2 S/130 (Roadster)', $schema['name']);
    }

    public function testBuildCarSchemaMapsFhcToCoupe(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => 'FHC']), '');

        $this->assertSame('Coupe', $schema['bodyType']);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
    }

    public function testBuildCarSchemaTrimsVariantBeforeBodyTypeMapLookup(): void
    {
        // Proves trim() happens BEFORE the BODY_TYPE_MAP lookup, not after — a padded
        // but non-empty variant (unlike the all-whitespace case tested elsewhere) would
        // silently fail to match "FHC" in the map if the ordering were ever reversed.
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => ' FHC ']), '');

        $this->assertSame('Coupe', $schema['bodyType']);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
    }

    public function testBuildCarSchemaMapsDhcToConvertible(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => 'DHC']), '');

        $this->assertSame('Convertible', $schema['bodyType']);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
    }

    public function testBuildCarSchemaFhcPreairflowSetsBothBodyTypeAndVehicleConfiguration(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => 'FHC-preairflow']), '');

        $this->assertSame('Coupe', $schema['bodyType']);
        $this->assertSame('FHC-preairflow', $schema['vehicleConfiguration']);
    }

    public function testBuildCarSchemaFederalIsExcludedFromBodyTypeButPreservedAsVehicleConfiguration(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => 'Federal']), '');

        $this->assertArrayNotHasKey('bodyType', $schema);
        $this->assertSame('Federal', $schema['vehicleConfiguration']);
    }

    public function testBuildCarSchemaRaceIsExcludedFromBodyTypeButPreservedAsVehicleConfiguration(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => 'Race']), '');

        $this->assertArrayNotHasKey('bodyType', $schema);
        $this->assertSame('Race', $schema['vehicleConfiguration']);
    }

    public function testBuildCarSchemaEmptySeriesWithVariantProducesNoDoubleSpace(): void
    {
        // Regression test: an earlier implementation concatenated year/series/variant
        // with a fixed space between each, so an empty series produced "Lotus Elan  (FHC)"
        // (two spaces). Reachable today only via unvalidated legacy data (the car_models
        // reference table always pairs a non-empty series with variant for validated input).
        $schema = CarView::buildCarSchema($this->makeCarData(['series' => '', 'variant' => 'FHC']), '');

        $this->assertSame('1969 Lotus Elan (FHC)', $schema['name']);
        $this->assertStringNotContainsString('  ', $schema['name']);
    }

    public function testBuildCarSchemaTrimsWhitespaceInColorChassisSeries(): void
    {
        // Real-data check (Issue #1371) found legacy untrimmed whitespace in these
        // columns, predating the current CarValidator/InputSanitizer::normalize()
        // input pipeline, which already trims on write.
        $schema = CarView::buildCarSchema($this->makeCarData([
            'series' => ' S4 ',
            'chassis' => ' 1234B ',
            'color' => ' Racing Green ',
        ]), '');

        $this->assertSame('1969 Lotus Elan S4 (Roadster)', $schema['name']);
        $this->assertSame('1234B', $schema['vehicleIdentificationNumber']);
        $this->assertSame('Racing Green', $schema['color']);
    }

    public function testBuildCarSchemaOmitsColorWhenEmpty(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['color' => '']), '');

        $this->assertArrayNotHasKey('color', $schema);
    }

    public function testBuildCarSchemaOmitsColorWhenWhitespaceOnly(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['color' => '   ']), '');

        $this->assertArrayNotHasKey('color', $schema);
    }

    public function testBuildCarSchemaOmitsBodyTypeAndVehicleConfigurationWhenVariantEmpty(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => '']), '');

        $this->assertArrayNotHasKey('bodyType', $schema);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
        $this->assertStringNotContainsString('(', $schema['name']);
    }

    public function testBuildCarSchemaWhitespaceOnlyVariantTreatedAsEmpty(): void
    {
        $schema = CarView::buildCarSchema($this->makeCarData(['variant' => '   ']), '');

        $this->assertArrayNotHasKey('bodyType', $schema);
        $this->assertArrayNotHasKey('vehicleConfiguration', $schema);
    }
}
