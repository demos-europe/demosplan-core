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

class FakePngGenerator extends FakeImageGenerator
{
    public function getFileExtension(): string
    {
        return 'png';
    }

    public function getMimeType(): string
    {
        return 'image/png';
    }

    protected function getRenderFunction(): string
    {
        return '\imagepng';
    }

    /**
     * The third argument to the imagepng function is the compression rate.
     * We want no compression for more predictable file sizes, thus the
     * parameter is `0`.
     *
     * {@inheritdoc}
     */
    protected function getRenderArgs(): array
    {
        return [0];
    }

    protected function getSizeConstraint(): array
    {
        return [19, 19, 1000];
    }
}
