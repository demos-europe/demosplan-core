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
    public function __construct(private readonly FileService $fileService)
    {
    }

    public function xmlify(string $html5, bool $removeWidthAttribute = false): string
    {
        $xml = str_replace('<br>', '<br/>', $html5);

        return preg_replace_callback(
            '/<img(.*?)(src="(.*?)")(.*?)\/?>/i',
            fn (array $matches) => $this->processImageTag($matches, $removeWidthAttribute),
            $xml
        );
    }

    private function processImageTag(array $matches, bool $removeWidthAttribute): string
    {
        $src = $matches[3];
        $srcParts = explode('/', $src);
        $hash = $srcParts[array_key_last($srcParts)];
        $absolutePath = $this->getAbsoluteImagePath($hash);
        $src = 'src="'.$absolutePath.'"';

        if ($removeWidthAttribute) {
            $matches[4] = $this->removeWidthAttribute($matches[4]);
        }

        return '<img'.$matches[1].$src.$matches[4].'/><br/>';
    }

    private function removeWidthAttribute(string $src): string
    {
        return preg_replace('/\s*width="[^"]*"/i', '', $src);
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
}
