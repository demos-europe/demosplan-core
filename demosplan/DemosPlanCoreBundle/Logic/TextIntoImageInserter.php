<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use GdImage;
use Psr\Log\LoggerInterface;

class TextIntoImageInserter
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Simple helper method to put a given text into an image.
     *
     * @param int    $imageHeight
     * @param string $text
     * @param int    $textSize
     */
    public function insert(GdImage $image, $imageHeight, $text, $textSize = 6): bool
    {
        try {
            $pathToFont = DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Resources/public/fonts/ptsansnarrow_regular/PTN57F-webfont.woff');
            if (function_exists('imagettftext')) {
                imagettftext($image, $textSize, 0, 3, $imageHeight - $textSize / 2, 0, $pathToFont, $text);

                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('error in textIntoImage()', [$e]);

            return false;
        }
    }
}
