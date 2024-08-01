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

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Exception;

/**
 * Handles the conversion of image tags in HTML content to clickable links with references.
 *
 * This class `ImageLinkConverter` is designed to process HTML content, specifically focusing on converting `<img>` tags
 * into clickable references. It manages a collection of image references, ensuring each image is uniquely identified
 * and accessible. The conversion process involves parsing the HTML, identifying image tags, and replacing them with
 * standardized clickable links that reference the images' absolute paths or identifiers.
 *
 * Usage involves creating an instance with a dependency on a `FileService` for resolving image paths, and then calling
 * the `convert` method with HTML content and an external identifier to process the content.
 */
final class ImageLinkConverter
{
    public const IMAGE_REFERENCE_RECOMMENDATION_SUFFIX = '_Darstellung_Erw_';
    private const IMAGE_REFERENCE_RECOMMENDATION_FORMAT = '%s'.self::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'%03d';
    /**
     * @var array<string, string>
     */
    private array $images = [];
    private int $imageCounter = 1;

    public function __construct(private readonly FileService $fileService)
    {
    }

    public function convert(string $html5, string $statementExternId, bool $asLinkedReference = true): string
    {
        $xml = str_replace('<br>', '<br/>', $html5);

        return preg_replace_callback(
            '/<img(.*?)(src="(.*?)")(.*?)\/?>/i',
            fn (array $matches) => $this->processImageTag($matches, $statementExternId, $asLinkedReference),
            $xml
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

        $this->images[$imageReference] = $this->getAbsoluteImagePath($hash);
        ++$this->imageCounter;

        return $imageReferenceLink;
    }

    private function getAbsoluteImagePath(string $hash): string
    {
        try {
            return $this->fileService->getFileInfo($hash)->getAbsolutePath();
        } catch (Exception) {
            // The src attribute probably didn't contain a hash --> assume it is a valid path instead.
            return trim($hash);
        }
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function resetImages(): void
    {
        $this->images = [];
        $this->imageCounter = 1;
    }
}
