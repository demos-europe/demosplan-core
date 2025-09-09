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

class FakeBmpGenerator extends FakeImageGenerator
{
    protected function getRenderFunction(): string
    {
        return '\imagebmp';
    }

    /**
     * Disable compression of the resulting file to
     * get predictable filesizes.
     *
     * {@inheritdoc}
     */
    protected function getRenderArgs(): array
    {
        return [false];
    }

    protected function getSizeConstraint(): array
    {
        return [19, 19, 1000];
    }

    public function getFileExtension(): string
    {
        return 'bmp';
    }

    public function getMimeType(): string
    {
        return 'image/bmp';
    }
}
