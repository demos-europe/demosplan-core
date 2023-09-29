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

class UCFirstExtension extends ExtensionBase
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'ucfirst', static function (
                    $string,
                    $encoding = 'UTF8'
                ) {
                    // http://stackoverflow.com/questions/31199959/twig-capitalize-makes-other-letters-small
                    $strlen = mb_strlen($string, $encoding);
                    $firstChar = mb_substr($string, 0, 1, $encoding);
                    $then = mb_substr($string, 1, $strlen - 1, $encoding);

                    return mb_strtoupper($firstChar, $encoding).$then;
                }
            ),
        ];
    }
}
