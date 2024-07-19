<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;


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
        if ([] === $images) {
            return;
        }
        $imageSpaceCurrentlyUsed = 0;
        $section->addPageBreak();
        foreach ($images as $imageReference => $imagePath) {
            [$width, $height] = getimagesize($imagePath);
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

            $section->addText($imageReference);
            $section->addBookmark($imageReference);
            $section->addImage($imagePath, $imageStyle);
        }

        // remove already printed images
        $this->imageLinkConverter->resetImages();
    }

    private function getMaxWidthAndHeight(): array
    {
        $maxWidth = self::MAX_WIDTH_INCH * self::STANDARD_DPI;
        $maxHeight = self::MAX_HEIGHT_INCH * self::STANDARD_DPI - self::STANDARD_PT_TEXT;

        return [$maxWidth, $maxHeight];
    }
}
