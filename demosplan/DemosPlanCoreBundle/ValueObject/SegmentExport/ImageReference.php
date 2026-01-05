<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class ImageReference.
 *
 * @method string getImagePath()
 * @method string getImageReference()
 * @method string getFileHash()
 */
class ImageReference extends ValueObject
{
    public function __construct(protected string $imageReference, protected string $imagePath, protected string $fileHash = '')
    {
        $this->lock();
    }
}
