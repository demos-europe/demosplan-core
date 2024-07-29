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

use demosplan\DemosPlanCoreBundle\Logic\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ImageReference;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\SimpleType\Jc;

class ImageManager
{
    private const STANDARD_DPI = 72;
    private const STANDARD_PT_TEXT = 10;
    private const MAX_WIDTH_INCH = 10.69;
    private const MAX_HEIGHT_INCH = 5.42;

    public function __construct(private readonly ImageLinkConverter $imageLinkConverter)
    {
    }

    public function addImages(Section $section): void
    {
        // Add images after all segments of one statement.
        $images = $this->imageLinkConverter->getImages();

        if (!$this->imagesArePresent($images)) {
            return;
        }

        $section->addPageBreak();

        foreach ($images as $imageReferencsArray) {
            $imageSpaceCurrentlyUsed = 0;
            foreach ($imageReferencsArray[ImageLinkConverter::IMAGES_KEY_SEGMENTS] as $imageReference) {
                $imageSpaceCurrentlyUsed = $this->addImage($section, $imageReference, $imageSpaceCurrentlyUsed);
            }
            foreach ($imageReferencsArray[ImageLinkConverter::IMAGES_KEY_RECOMMENDATION] as $imageReference) {
                $imageSpaceCurrentlyUsed = $this->addImage($section, $imageReference, $imageSpaceCurrentlyUsed);
            }
        }

        // remove already printed images
        $this->imageLinkConverter->resetImages();
    }

    private function imagesArePresent(array $images): bool
    {
        $imagesArePresent = false;
        foreach ($images as $imageReferencsArray) {
            if ([] !== $imageReferencsArray[ImageLinkConverter::IMAGES_KEY_SEGMENTS]
                || [] !== $imageReferencsArray[ImageLinkConverter::IMAGES_KEY_RECOMMENDATION]) {
                $imagesArePresent = true;
                break;
            }
        }

        return $imagesArePresent;
    }

    private function addImage(Section $section, ImageReference $imageReference, float $imageSpaceCurrentlyUsed): float
    {
        [$width, $height] = getimagesize($imageReference->getImagePath());
        [$maxWidth, $maxHeight] = $this->getMaxWidthAndHeight();

        if ($width > $maxWidth) {
            $factor = $maxWidth / $width;
            $width = $maxWidth;
            $height *= $factor;
        }
        if ($height > $maxHeight) {
            $factor = $maxHeight / $height;
            $height = $maxHeight;
            $width *= $factor;
        }
        if ($height > $maxHeight - $imageSpaceCurrentlyUsed) {
            $section->addPageBreak();
        }
        $imageSpaceCurrentlyUsed += $height + self::STANDARD_PT_TEXT * 2;

        $imageStyle = [
            'width'  => $width,
            'height' => $height,
            'align'  => Jc::START,
        ];

        $section->addText($imageReference->getImageReference());
        $section->addBookmark($imageReference->getImageReference());
        $section->addImage($imageReference->getImagePath(), $imageStyle);

        return $imageSpaceCurrentlyUsed;
    }

    private function getMaxWidthAndHeight(): array
    {
        $maxWidth = self::MAX_WIDTH_INCH * self::STANDARD_DPI;
        $maxHeight = self::MAX_HEIGHT_INCH * self::STANDARD_DPI - self::STANDARD_PT_TEXT;

        return [$maxWidth, $maxHeight];
    }
}
