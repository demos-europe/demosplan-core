<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Twig\TwigFilter;

class JsonDecodeExtension extends ExtensionBase
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'json_decode', function ($json, $assoc = false, $depth = 512, $options = 0) {
                    return json_decode($json, $assoc, $depth, $options);
                }
            ),
        ];
    }
}
