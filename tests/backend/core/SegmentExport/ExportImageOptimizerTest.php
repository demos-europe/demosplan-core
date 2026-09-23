<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\SegmentExport;

use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ExportImageOptimizer;
use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Tests\Base\FunctionalTestCase;

class ExportImageOptimizerTest extends FunctionalTestCase
{
    /** @var ExportImageOptimizer */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ExportImageOptimizer::class);
    }

    public function testLargeOpaquePngBecomesSmallerJpeg(): void
    {
        $fs = new Filesystem();
        $sourcePath = $this->createNoisyPng(300, 300, false);
        $sourceSize = filesize($sourcePath);

        $optimizedPath = $this->sut->optimize($sourcePath);

        static::assertNotSame($sourcePath, $optimizedPath);
        static::assertLessThan($sourceSize, filesize($optimizedPath));
        static::assertFileDoesNotExist($sourcePath);

        $info = getimagesize($optimizedPath);
        static::assertSame('image/jpeg', $info['mime']);

        $fs->remove([$sourcePath, $optimizedPath]);
    }

    public function testPngWithAlphaStaysPng(): void
    {
        $fs = new Filesystem();
        $sourcePath = $this->createNoisyPng(300, 300, true);

        $optimizedPath = $this->sut->optimize($sourcePath);

        $info = getimagesize($optimizedPath);
        static::assertSame('image/png', $info['mime']);

        $fs->remove([$sourcePath, $optimizedPath]);
    }

    public function testSmallImageStaysUnchanged(): void
    {
        $fs = new Filesystem();
        $sourcePath = $this->createNoisyPng(10, 10, false);

        $optimizedPath = $this->sut->optimize($sourcePath);

        static::assertSame($sourcePath, $optimizedPath);

        $fs->remove($sourcePath);
    }

    public function testImageAboveDecodeLimitStaysUnchanged(): void
    {
        $fs = new Filesystem();
        $sourcePath = $this->createPngHeaderOnly(10000, 6000);

        // Decoding the header-only file would fail and fall back too, so assert the guard's own log.
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info')->with('Export image too large to optimize, using original');
        $logger->expects(self::never())->method('warning');
        $sut = new ExportImageOptimizer($this->getContainer()->get(ImageManager::class), $logger);

        $optimizedPath = $sut->optimize($sourcePath);

        static::assertSame($sourcePath, $optimizedPath);
        static::assertFileExists($sourcePath);

        $fs->remove($sourcePath);
    }

    public function testCorruptFileFallsBackToOriginal(): void
    {
        $fs = new Filesystem();
        $sourcePath = tempnam(sys_get_temp_dir(), 'dplan_test_corrupt_');
        file_put_contents($sourcePath, 'not an image');

        $optimizedPath = $this->sut->optimize($sourcePath);

        static::assertSame($sourcePath, $optimizedPath);

        $fs->remove($sourcePath);
    }

    private function createNoisyPng(int $width, int $height, bool $withAlpha): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('dplan_test_image_', true).'.png';

        $image = imagecreatetruecolor($width, $height);
        if ($withAlpha) {
            imagesavealpha($image, true);
        }

        // Random per-pixel noise, so the PNG compresses poorly and a JPEG re-encode
        // actually shrinks the file - a solid color image would defeat that assertion.
        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $color = $withAlpha
                    ? imagecolorallocatealpha($image, random_int(0, 255), random_int(0, 255), random_int(0, 255), 64)
                    : imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255));
                imagesetpixel($image, $x, $y, $color);
            }
        }

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * Only the signature and IHDR chunk, which is all getimagesize() reads, so the dimensions
     * can be huge without allocating the pixels.
     */
    private function createPngHeaderOnly(int $width, int $height): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('dplan_test_image_', true).'.png';
        $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        file_put_contents($path, "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr)));

        return $path;
    }
}
