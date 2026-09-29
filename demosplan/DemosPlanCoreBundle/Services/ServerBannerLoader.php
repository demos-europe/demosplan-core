<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Parsedown;

class ServerBannerLoader
{
    private const BANNER_FILENAME = 'SERVER_BANNER.md';

    public function getServerBanner(): ?string
    {
        $path = DemosPlanPath::getRootPath(self::BANNER_FILENAME);

        if (!is_readable($path)) {
            return null;
        }
        $content = file_get_contents($path);

        if ('' === $content) {
            return null;
        }

        return (new Parsedown())->text($content);
    }
}
