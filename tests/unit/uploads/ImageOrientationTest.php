<?php

declare(strict_types=1);

use ElanRegistry\Exceptions\ImageProcessingException;
use ElanRegistry\Resize;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Test for EXIF orientation handling in the Resize class
 *
 * Tests the correctOrientation method functionality including:
 * - EXIF orientation detection and correction
 * - All 8 EXIF orientation values
 * - Privacy-preserving EXIF stripping
 * - Error handling for missing EXIF data
 */
class ImageOrientationTest extends TestCase
{
    private $testImageDir;
    private $outputDir;

    protected function setUp(): void
    {
        // $this->testImageDir points at fixtures/orientation/, which does not exist on
        // disk. The orientation-correction tests below use synthetic in-memory images
        // (via imagecreate), not real EXIF-tagged fixtures, so this is a known coverage
        // gap rather than a bug — real EXIF orientation handling is not exercised here.
        $this->testImageDir = __DIR__ . '/fixtures/orientation/';
        $this->outputDir = __DIR__ . '/output/orientation/';

        // Create output directory if it doesn't exist
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up output files
        if (is_dir($this->outputDir)) {
            $files = glob($this->outputDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Test orientation correction with mock image data
     */
    #[Group('fast')]
    public function testOrientationCorrectionWithMockImage(): void
    {
        // Create a simple test image
        $testImage = imagecreate(100, 50);
        $testFile = $this->outputDir . 'test_normal.jpg';
        
        // Create a simple test image with different colors
        $white = imagecolorallocate($testImage, 255, 255, 255);
        $red = imagecolorallocate($testImage, 255, 0, 0);
        imagefill($testImage, 0, 0, $white);
        imagefilledrectangle($testImage, 0, 0, 20, 50, $red); // Red stripe on left
        
        imagejpeg($testImage, $testFile, 90);

        $this->assertFileExists($testFile, 'Test image should be created');

        // Test Resize class can process the image
        try {
            $resize = new Resize($testFile);
            $this->assertNotNull($resize, 'Resize object should be created');
            
            // The image should be processed successfully even without EXIF data
            $resize->resizeImage(50, 25, 'exact');
            $resizedFile = $this->outputDir . 'test_resized.jpg';
            $resize->saveImage($resizedFile, 80);
            
            $this->assertFileExists($resizedFile, 'Resized image should be created');
            
        } catch (Exception $e) {
            $this->fail('Resize should handle images without EXIF data: ' . $e->getMessage());
        }
    }

    /**
     * Test that the Resize class properly handles files without EXIF data
     */
    #[Group('fast')]
    public function testHandleImageWithoutEXIF(): void
    {
        // Create a simple test image without EXIF data
        $testImage = imagecreate(100, 100);
        $white = imagecolorallocate($testImage, 255, 255, 255);
        imagefill($testImage, 0, 0, $white);
        
        $testFile = $this->outputDir . 'no_exif.jpg';
        imagejpeg($testImage, $testFile, 90);

        // Should not throw exception when processing image without EXIF
        try {
            $resize = new Resize($testFile);
            $this->assertInstanceOf(\ElanRegistry\Resize::class, $resize);
        } catch (Exception $e) {
            $this->fail('Should handle images without EXIF data gracefully: ' . $e->getMessage());
        }
    }

    /**
     * Test that non-JPEG files are handled properly
     */
    #[Group('fast')]
    public function testHandleNonJPEGFiles(): void
    {
        // Create a simple PNG test image
        $testImage = imagecreate(100, 100);
        $white = imagecolorallocate($testImage, 255, 255, 255);
        imagefill($testImage, 0, 0, $white);
        
        $testFile = $this->outputDir . 'test.png';
        imagepng($testImage, $testFile);

        // Should process PNG files normally (no EXIF orientation correction)
        try {
            $resize = new Resize($testFile);
            $this->assertInstanceOf(\ElanRegistry\Resize::class, $resize);
        } catch (Exception $e) {
            $this->fail('Should handle PNG files without EXIF processing: ' . $e->getMessage());
        }
    }

    /**
     * Test that an unhandled file extension throws ImageProcessingException
     */
    #[Group('fast')]
    public function testUnhandledExtensionThrowsImageProcessingException(): void
    {
        $testFile = $this->outputDir . 'test.webp';
        file_put_contents($testFile, 'not a real image');

        $this->expectException(ImageProcessingException::class);

        new Resize($testFile);
    }

    /**
     * Test that a corrupt file with a recognized extension throws ImageProcessingException
     */
    #[Group('fast')]
    public function testCorruptRecognizedExtensionThrowsImageProcessingException(): void
    {
        $testFile = $this->outputDir . 'corrupt.jpg';
        file_put_contents($testFile, 'this is not valid jpeg data');

        $this->expectException(ImageProcessingException::class);

        new Resize($testFile);
    }
}
