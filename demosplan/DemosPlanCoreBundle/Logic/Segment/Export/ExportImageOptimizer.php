<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use Intervention\Image\ImageManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Downscales and re-encodes images staged for a segments export, so a DOCX/ODT
 * full of unmodified screenshots doesn't balloon in size. Runs once per unique
 * staged image, right after {@see ImageLinkConverter} stages it locally.
 */
class ExportImageOptimizer
{
    // Fits PhpWord's display box of 10.69 x 5.42 inch at ~150 dpi.
    private const MAX_WIDTH = 1600;
    private const MAX_HEIGHT = 815;
    private const JPEG_QUALITY = 80;
    // GD holds ~4 bytes per pixel, plus a copy when auto-orienting. Keeps a decode under
    // ~400 MB, well below the 2G php-fpm limit. Running out of memory is fatal, not catchable.
    private const MAX_DECODE_PIXELS = 50_000_000;

    public function __construct(
        private readonly ImageManager $imageManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns the path of an optimized copy of $sourcePath placed next to it and deletes
     * $sourcePath, or returns $sourcePath unchanged if optimizing failed, was skipped, or
     * didn't shrink the file.
     */
    public function optimize(string $sourcePath): string
    {
        try {
            $info = @getimagesize($sourcePath);
            if (false === $info) {
                return $sourcePath;
            }

            if ($info[0] * $info[1] > self::MAX_DECODE_PIXELS) {
                $this->logger->info('Export image too large to optimize, using original', [
                    'path'   => $sourcePath,
                    'width'  => $info[0],
                    'height' => $info[1],
                ]);

                return $sourcePath;
            }

            $preserveAlpha = 'image/png' === $info['mime'] && $this->hasAlphaChannel($sourcePath);

            $image = $this->imageManager->read($sourcePath)->scaleDown(self::MAX_WIDTH, self::MAX_HEIGHT);
            $encoded = $preserveAlpha
                ? $image->toPng()
                : $image->toJpeg(quality: self::JPEG_QUALITY);

            $optimizedPath = $sourcePath.'.optimized.'.($preserveAlpha ? 'png' : 'jpg');
            $encoded->save($optimizedPath);

            if (filesize($optimizedPath) >= filesize($sourcePath)) {
                unlink($optimizedPath);

                return $sourcePath;
            }

            unlink($sourcePath);

            return $optimizedPath;
        } catch (Throwable $exception) {
            $this->logger->warning('Could not optimize export image, using original', [
                'path'  => $sourcePath,
                'error' => $exception->getMessage(),
            ]);

            return $sourcePath;
        }
    }

    private function hasAlphaChannel(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (false === $handle) {
            return false;
        }

        // PNG signature (8 bytes) + IHDR chunk length/type (8 bytes) + width/height (8 bytes) +
        // bit depth (1 byte) puts the colour type byte at offset 25. Colour type 4 (grayscale+alpha)
        // and 6 (truecolor+alpha) are the formats that need an alpha channel preserved.
        fseek($handle, 25);
        $colorType = ord(fread($handle, 1));
        fclose($handle);

        return in_array($colorType, [4, 6], true);
    }
}
