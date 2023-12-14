<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataGenerator\Images;

use demosplan\DemosPlanCoreBundle\DataGenerator\CustomFactory\DataGeneratorInterface;

use function call_user_func_array;
use function imagecolorallocate;
use function imagecreatetruecolor;
use function imagefill;
use function ob_get_clean;
use function ob_start;

abstract class FakeImageGenerator implements DataGeneratorInterface
{
    /**
     * gd function to use for output.
     */
    abstract protected function getRenderFunction(): string;

    /**
     * Optional arguments passed to the render function **after**
     * image context and output stream.
     *
     * @return array<int,mixed>
     */
    abstract protected function getRenderArgs(): array;

    /**
     * Width and height to achieve approximately 1kB image size.
     * 3rd parameter is the maximum allowed multiplier for the sides to avoid errors.
     *
     * @return array<int,int>
     */
    abstract protected function getSizeConstraint(): array;

    public function generate(int $approximateSizeInBytes): string
    {
        $approximateSizeInKibiBytes = (int) ($approximateSizeInBytes / 1024);
        if (0 === $approximateSizeInKibiBytes) {
            $approximateSizeInKibiBytes = 1;
        }

        [$width, $height, $maxSideMultiplier] = $this->getSizeConstraint();

        // translate max size into dimension multiplier to allow for big jpgs
        $sideMultiplier = (int) ceil(sqrt($approximateSizeInKibiBytes));

        // if a jpg is too big, generate biggest possible
        if ($sideMultiplier > $maxSideMultiplier) {
            $sideMultiplier = $maxSideMultiplier;
        }

        $gd = imagecreatetruecolor($width * $sideMultiplier, $height * $sideMultiplier);

        $color = imagecolorallocate($gd, 0, 0, 0);
        imagefill($gd, 1, 1, $color);

        ob_start();

        call_user_func_array(
            $this->getRenderFunction(),
            [$gd, null, ...$this->getRenderArgs()]
        );

        return ob_get_clean();
    }
}
