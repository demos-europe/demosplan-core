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

class HumanFilesizeExtension extends ExtensionBase
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('humanFilesize', [$this, 'formatHumanFilesize']),
        ];
    }

    public function formatHumanFilesize($bytes, $precision = 2)
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);

        $pow = floor((($bytes > 0) ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision).' '.$units[$pow];
    }
}
