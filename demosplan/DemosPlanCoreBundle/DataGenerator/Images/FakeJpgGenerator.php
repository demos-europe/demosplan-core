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

class FakeJpgGenerator extends FakeImageGenerator
{
    protected function getRenderFunction(): string
    {
        return '\imagejpeg';
    }

    /**
     * The third parameter to imagejpeg() is the image quality.
     * As with the other compressed formats, we want as little compression
     * as possible for reliable output sizes. Thus the maximum quality
     * `100` is set.
     *
     * {@inheritdoc}
     */
    protected function getRenderArgs(): array
    {
        return [100];
    }

    protected function getSizeConstraint(): array
    {
        return [200, 200, 230];
    }

    public function getFileExtension(): string
    {
        return 'jpg';
    }

    public function getMimeType(): string
    {
        return 'image/jpeg';
    }
}
