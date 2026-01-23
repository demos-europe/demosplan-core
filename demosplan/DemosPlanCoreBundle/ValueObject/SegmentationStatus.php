<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

enum SegmentationStatus: string
{
    case UNSEGMENTED = 'unsegmented';
    case SEGMENTED = 'segmented';

    public function isSegmented(): bool
    {
        return self::SEGMENTED === $this;
    }
}
