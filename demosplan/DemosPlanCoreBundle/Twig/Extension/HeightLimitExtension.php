<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use demosplan\DemosPlanCoreBundle\Services\HTMLFragmentSlicer;
use Twig\TwigFilter;

class HeightLimitExtension extends ExtensionBase
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('heightLimitShorten', $this->heightLimitShorten(...)),
        ];
    }

    /**
     * Only return the data from the fragment slicer to use it with the DpHeightLimit Vue component.
     *
     * @param string $content
     * @param int    $maxNbCharcters
     */
    public function heightLimitShorten($content, $maxNbCharcters = 500): string
    {
        return HTMLFragmentSlicer::getShortened($content, $maxNbCharcters);
    }
}
