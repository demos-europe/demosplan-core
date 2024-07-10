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

use Exception;

final class Xmlifier
{
    /**
     * @var array<string, string>
     */
    private array $images = [];
    private int $imageCounter = 1;

    public function __construct(private readonly FileService $fileService)
    {
    }

    public function xmlify(string $html5, string $statementExternId): string
    {
        $xml = str_replace('<br>', '<br/>', $html5);

        return preg_replace_callback(
            '/<img(.*?)(src="(.*?)")(.*?)\/?>/i',
            fn (array $matches) => $this->processImageTag($matches, $statementExternId),
            $xml
        );
    }

    private function processImageTag(array $matches, string $statementExternId): string
    {
        $src = $matches[3];
        $srcParts = explode('/', $src);
        $hash = $srcParts[array_key_last($srcParts)];

        $imageReference = $statementExternId.'_Darstellung_Erw_'.$this->imageCounter;
        $imageReferenceLink = '<a href="#' . $imageReference . '" style="color: blue; text-decoration: underline;">'
            . $imageReference . '</a>';
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
