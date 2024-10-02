<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ConvertedSegment;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ImageReference;
use Exception;

final class ImageLinkConverter
{
    public const IMAGE_REFERENCE_RECOMMENDATION_SUFFIX = '_Darstellung_Erw_';
    public const IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX = '_Darstellung_Stell_';
    private const IMAGE_REFERENCE_RECOMMENDATION_FORMAT = '%s'.self::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'%03d';
    public const IMAGES_KEY_RECOMMENDATION = 'recommendation';
    public const IMAGES_KEY_SEGMENTS = 'segments';
    /**
     * @var array<int, array<string, array<int, ImageReference>>>
     */
    private array $images = [];
    /**
     * @var array<int, ImageReference>
     */
    private array $currentImagesFromRecommendationText = [];
    private int $imageCounter = 1;

    public function __construct(private readonly HtmlHelper $htmlHelper, private readonly FileService $fileService)
    {
    }

    public function convert(
        Segment $segment,
        string $statementExternId,
        bool $asLinkedReference = true,
    ): ConvertedSegment {
        $segmentText = $segment->getText();
        $recommendationText = $segment->getRecommendation();
        $xmlSegmentText = str_replace('<br>', '<br/>', $segmentText);
        $xmlRecommendationText = str_replace('<br>', '<br/>', $recommendationText);

        $prefix = $statementExternId.'_';
        $imageReferencesFromSegmentText = $this->htmlHelper->extractImageDataByClass(
            $xmlSegmentText,
            HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL,
            $prefix
        );

        foreach ($imageReferencesFromSegmentText as $index => $imageReference) {
            $hash = $imageReference->getFileHash();
            $path = $this->getAbsoluteImagePath($hash);
            $imageReferencesFromSegmentText[$index] = new ImageReference(
                $imageReference->getImageReference(),
                $path,
                $hash
            );
        }

        $xmlSegmentText = $this->updateSegmentText($asLinkedReference, $xmlSegmentText, $prefix);

        $this->resetCurrentImagesFromRecommendationText();
        $xmlRecommendationText = $this->replaceImagesWithTextReferences(
            $xmlRecommendationText,
            $statementExternId,
            $asLinkedReference
        );

        $this->images[] = [
            self::IMAGES_KEY_RECOMMENDATION => $this->currentImagesFromRecommendationText,
            self::IMAGES_KEY_SEGMENTS       => $imageReferencesFromSegmentText,
        ];

        return new ConvertedSegment($xmlSegmentText, $xmlRecommendationText);
    }

    private function updateSegmentText(bool $asLinkedReference, string $text, string $prefix): string
    {
        $asLinkedReference
            ? $text = $this->htmlHelper->updateLinkTextWithClass(
                $text,
                HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL,
                $prefix
            )
            : $text = $this->htmlHelper->removeLinkTagsByClass(
                $text,
                HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL,
                $prefix
            );

        return $text;
    }

    private function replaceImagesWithTextReferences(string $html, string $statementExternId, bool $asLinkedReference): string
    {
        return preg_replace_callback(
            '/<img(.*?)(src="(.*?)")(.*?)\/?>/i',
            fn (array $matches) => $this->processImageTag($matches, $statementExternId, $asLinkedReference),
            $html
        );
    }

    private function processImageTag(array $matches, string $statementExternId, bool $asLinkedReference): string
    {
        $src = $matches[3];
        $srcParts = explode('/', $src);
        $hash = $srcParts[array_key_last($srcParts)];

        $imageReference =
            sprintf(self::IMAGE_REFERENCE_RECOMMENDATION_FORMAT, $statementExternId, $this->imageCounter);
        $imageReferenceLink = $imageReference;
        if ($asLinkedReference) {
            $imageReferenceLink = '<a href="#'.$imageReference.'" style="color: blue; text-decoration: underline;">'
                .$imageReference.'</a>';
        }

        $image = new ImageReference($imageReference, $this->getAbsoluteImagePath($hash));
        $this->currentImagesFromRecommendationText[] = $image;
        ++$this->imageCounter;

        return $imageReferenceLink;
    }

    private function getAbsoluteImagePath(string $hash): string
    {
        try {
            return $this->fileService->ensureLocalFileFromHash($hash);
        } catch (Exception) {
            // The src attribute probably didn't contain a hash --> assume it is a valid path instead.
            return trim($hash);
        }
    }

    private function resetCurrentImagesFromRecommendationText(): void
    {
        $this->currentImagesFromRecommendationText = [];
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function resetImages(): void
    {
        // temporary images are needed during export afterwards, they cannot be removed
        $this->images = [];
        $this->imageCounter = 1;
    }
}
