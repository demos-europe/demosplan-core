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
 * @method string getText()
 * @method string getRecommendationText()
 */
class ConvertedSegment extends ValueObject
{
    protected string $text;
    protected string $recommendationText;

    public function __construct(string $text, string $recommendationText)
    {
        $this->text = $text;
        $this->recommendationText = $recommendationText;

        $this->lock();
    }
}
