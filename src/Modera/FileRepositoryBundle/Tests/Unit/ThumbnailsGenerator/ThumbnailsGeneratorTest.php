<?php

namespace Modera\FileRepositoryBundle\Tests\Unit\ThumbnailsGenerator;

use Modera\FileRepositoryBundle\ThumbnailsGenerator\NotImageGivenException;
use Modera\FileRepositoryBundle\ThumbnailsGenerator\ThumbnailsGenerator;
use Symfony\Component\HttpFoundation\File\File;

class ThumbnailsGeneratorTest extends \PHPUnit\Framework\TestCase
{
    private ThumbnailsGenerator $generator;

    /** @var string[] */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        $this->generator = new ThumbnailsGenerator();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
    }

    public function testGenerateThrowsExceptionForNonImage(): void
    {
        $path = \tempnam(\sys_get_temp_dir(), 'tg_test_').'.bin';
        \file_put_contents($path, 'not an image');
        $this->tempFiles[] = $path;

        $this->expectException(NotImageGivenException::class);

        $this->generator->generate(new File($path), 100, 100);
    }

    /**
     * Non-JPEG files must pass through without any EXIF processing.
     */
    public function testGenerateNonJpegProducesCorrectThumbnail(): void
    {
        $result = $this->generator->generate(new File($this->fixturePath('backend.png')), 50, 50);
        $this->tempFiles[] = $result;

        $this->assertFileExists($result);

        [$w, $h] = \getimagesize($result);
        $this->assertLessThanOrEqual(50, $w);
        $this->assertLessThanOrEqual(50, $h);
    }

    /**
     * A plain JPEG without any EXIF block must be thumbnailed by raw pixel dimensions.
     * 200x100 landscape → inset 100x100 box → 100x50.
     */
    public function testGenerateJpegWithoutExifBlock(): void
    {
        $source = $this->createPlainJpeg(200, 100);
        $this->tempFiles[] = $source;

        $result = $this->generator->generate(new File($source), 100, 100);
        $this->tempFiles[] = $result;

        [$w, $h] = \getimagesize($result);
        $this->assertSame(100, $w);
        $this->assertSame(50, $h);
    }

    /**
     * Orientations 1-4 keep the same aspect ratio (no 90° rotation).
     * A 200x100 source fits into a 100x100 inset box as 100x50.
     *
     * @dataProvider orientationsWithoutDimensionSwapProvider
     */
    public function testGenerateKeepsDimensionsForNonRotatingOrientations(int $orientation): void
    {
        if (!\function_exists('exif_read_data')) {
            $this->markTestSkipped('PHP exif extension not available');
        }

        $source = $this->createJpegWithExifOrientation($orientation, 200, 100);
        $this->tempFiles[] = $source;

        $result = $this->generator->generate(new File($source), 100, 100);
        $this->tempFiles[] = $result;

        [$w, $h] = \getimagesize($result);
        $this->assertSame(100, $w, "Width mismatch for orientation=$orientation");
        $this->assertSame(50, $h, "Height mismatch for orientation=$orientation");
    }

    public static function orientationsWithoutDimensionSwapProvider(): array
    {
        return [
            'orientation 1 (normal)' => [1],
            'orientation 2 (flip H)' => [2],
            'orientation 3 (180°)' => [3],
            'orientation 4 (flip V)' => [4],
        ];
    }

    /**
     * Orientations 5-8 include a 90° rotation, effectively swapping width and height.
     * A 200x100 landscape source becomes 100x200 portrait after rotation.
     * Fitting 100x200 into a 100x100 inset box → 50x100.
     *
     * @dataProvider orientationsWithDimensionSwapProvider
     */
    public function testGenerateSwapsDimensionsFor90DegreeOrientations(int $orientation): void
    {
        if (!\function_exists('exif_read_data')) {
            $this->markTestSkipped('PHP exif extension not available');
        }

        $source = $this->createJpegWithExifOrientation($orientation, 200, 100);
        $this->tempFiles[] = $source;

        $result = $this->generator->generate(new File($source), 100, 100);
        $this->tempFiles[] = $result;

        [$w, $h] = \getimagesize($result);
        $this->assertSame(50, $w, "Expected portrait thumbnail width for orientation=$orientation");
        $this->assertSame(100, $h, "Expected portrait thumbnail height for orientation=$orientation");
    }

    public static function orientationsWithDimensionSwapProvider(): array
    {
        return [
            'orientation 5 (rotate 90° CW + flip H)' => [5],
            'orientation 6 (rotate 90° CW)' => [6],
            'orientation 7 (rotate 90° CCW + flip H)' => [7],
            'orientation 8 (rotate 90° CCW)' => [8],
        ];
    }

    /**
     * Orientation 3 is the originally reported bug: images appeared upside-down.
     * The source has its left half red and right half blue.
     * After 180° rotation the colour halves are mirrored, so the top-left pixel
     * of the thumbnail must shift from red (orientation=1) to blue (orientation=3).
     */
    public function testGenerateOrientation3RotatesPixels180Degrees(): void
    {
        if (!\function_exists('exif_read_data')) {
            $this->markTestSkipped('PHP exif extension not available');
        }

        $sourceNormal = $this->createJpegWithExifOrientation(1, 200, 100);
        $sourceFlipped = $this->createJpegWithExifOrientation(3, 200, 100);
        $this->tempFiles = \array_merge($this->tempFiles, [$sourceNormal, $sourceFlipped]);

        $resultNormal = $this->generator->generate(new File($sourceNormal), 100, 100);
        $resultFlipped = $this->generator->generate(new File($sourceFlipped), 100, 100);
        $this->tempFiles = \array_merge($this->tempFiles, [$resultNormal, $resultFlipped]);

        // Probe pixel at (10, 5) — well within the left quarter of the 100x50 thumbnail.
        $rgbNormal = $this->samplePixel($resultNormal, 10, 5);
        $rgbFlipped = $this->samplePixel($resultFlipped, 10, 5);

        $this->assertGreaterThan(
            $rgbNormal['blue'],
            $rgbNormal['red'],
            'Orientation 1: left-side pixel should be red'
        );
        $this->assertGreaterThan(
            $rgbFlipped['red'],
            $rgbFlipped['blue'],
            'Orientation 3: left-side pixel should be blue after 180° rotation'
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Creates a plain 200x100 JPEG (left half red, right half blue) without any EXIF block.
     */
    private function createPlainJpeg(int $width, int $height): string
    {
        $gd = \imagecreatetruecolor($width, $height);
        $red = \imagecolorallocate($gd, 255, 0, 0);
        $blue = \imagecolorallocate($gd, 0, 0, 255);
        \imagefilledrectangle($gd, 0, 0, (int) ($width / 2) - 1, $height - 1, $red);
        \imagefilledrectangle($gd, (int) ($width / 2), 0, $width - 1, $height - 1, $blue);

        $path = \tempnam(\sys_get_temp_dir(), 'tg_plain_').'.jpg';
        \imagejpeg($gd, $path, 95);
        \imagedestroy($gd);

        return $path;
    }

    /**
     * Creates a JPEG (left half red, right half blue) with a specific EXIF Orientation tag.
     *
     * The EXIF APP1 block is constructed manually and inserted right after the SOI marker,
     * replacing any existing APP0 (JFIF) segment since EXIF and JFIF are incompatible.
     */
    private function createJpegWithExifOrientation(int $orientation, int $width = 200, int $height = 100): string
    {
        $gd = \imagecreatetruecolor($width, $height);
        $red = \imagecolorallocate($gd, 255, 0, 0);
        $blue = \imagecolorallocate($gd, 0, 0, 255);
        \imagefilledrectangle($gd, 0, 0, (int) ($width / 2) - 1, $height - 1, $red);
        \imagefilledrectangle($gd, (int) ($width / 2), 0, $width - 1, $height - 1, $blue);

        \ob_start();
        \imagejpeg($gd, null, 95);
        $jpegData = (string) \ob_get_clean();
        \imagedestroy($gd);

        // Build a minimal EXIF APP1 segment with a single Orientation IFD entry.
        //
        // Structure (all offsets relative to start of TIFF header inside APP1):
        //   "Exif\0\0"          6 bytes  – APP1 identifier
        //   "II"                2 bytes  – byte order: little-endian
        //   0x002A              2 bytes  – TIFF magic number
        //   0x00000008          4 bytes  – offset to IFD0 (= 8, immediately follows header)
        //   0x0001              2 bytes  – number of IFD entries
        //   tag=0x0112          2 bytes  – Orientation
        //   type=0x0003         2 bytes  – SHORT
        //   count=1             4 bytes
        //   value               4 bytes  – orientation value (2 bytes LE, padded to 4)
        //   0x00000000          4 bytes  – next IFD offset (none)
        $orientationBytes = \pack('v', $orientation); // 2-byte little-endian short
        $exifContent = "Exif\x00\x00"
            ."\x49\x49"             // byte order: little-endian ('II')
            ."\x2A\x00"             // TIFF magic number
            ."\x08\x00\x00\x00"     // offset to IFD0 = 8
            ."\x01\x00"             // IFD0 entry count: 1
            ."\x12\x01"             // tag: 0x0112 = Orientation
            ."\x03\x00"             // type: SHORT (2 bytes per value)
            ."\x01\x00\x00\x00"     // count: 1
            .$orientationBytes."\x00\x00" // value padded to 4 bytes
            ."\x00\x00\x00\x00"     // next IFD offset: 0 (no more IFDs)
        ;
        // APP1 length field includes the 2 bytes of the field itself.
        $app1Length = \strlen($exifContent) + 2;
        $app1Block = "\xFF\xE1".\pack('n', $app1Length).$exifContent;

        // Determine the insert position: right after the 2-byte SOI marker.
        // If GD prepended an APP0 (JFIF) segment, skip past it to avoid having
        // both JFIF and EXIF markers in the same file.
        $insertAt = 2;
        if ("\xFF\xE0" === \substr($jpegData, 2, 2)) {
            // APP0 length field (bytes 4–5) includes itself → total APP0 size = 2 + length.
            $app0Length = (\ord($jpegData[4]) << 8) | \ord($jpegData[5]);
            $insertAt = 2 + 2 + $app0Length; // skip SOI + APP0 marker + APP0 payload
        }

        $jpegWithExif = \substr($jpegData, 0, 2).$app1Block.\substr($jpegData, $insertAt);

        $path = \tempnam(\sys_get_temp_dir(), 'tg_exif_').'.jpg';
        \file_put_contents($path, $jpegWithExif);

        return $path;
    }

    /**
     * @return array{red: int, green: int, blue: int}
     */
    private function samplePixel(string $imagePath, int $x, int $y): array
    {
        $gd = \imagecreatefromjpeg($imagePath);
        $rgb = \imagecolorsforindex($gd, \imagecolorat($gd, $x, $y));
        \imagedestroy($gd);

        return $rgb;
    }

    private function fixturePath(string $filename): string
    {
        return __DIR__.'/../../Fixtures/'.$filename;
    }
}
