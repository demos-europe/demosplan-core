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
     * @var FileService
     */
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Take a shot at transforming roughly valid html5 into valid xml.
     */
    public function xmlify(string $html5): string
    {
        $xml = str_replace('<br>', '<br/>', $html5);
        $xml = preg_replace_callback(
            '/<img(.*?)(src="(.*?)")(.*?)\/?>/i',
            function (array $matches) {
                $src = $matches[3];
                $srcParts = explode('/', $src);

                $hash = $srcParts[array_key_last($srcParts)];

                try {
                    $file = $this->fileService->getFileInfo($hash);
                    $absolutePath = $file->getAbsolutePath();
                } catch (Exception $e) {
                    // if the hash lookup fails, the src attribute probably didn't contain
                    // a hash --> assume it is a valid path instead
                    $absolutePath = trim($hash);
                }

                $src = 'src="'.$absolutePath.'"';
                // $matches[4] = $this->removeWidthAttribute($matches[4]);

                return '<img'.$matches[1].$src.$matches[4].'/><br/>';
            },
            $xml
        );

        return $xml;
    }

    private function removeWidthAttribute(string $src): string
    {
        return preg_replace('/\s*width=".*?"/i', '', $src);
    }
}
