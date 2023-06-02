<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

class UpdateException extends RuntimeException
{
    public static function alreadyRunning(): self
    {
        return new self('Update already running. If this is not true, delete file update.lock');
    }

    public static function branchSwitchNotAllowed(): self
    {
        return new self('Checkout branch when performing dplan:update in dev mode is invalid');
    }

    public static function assetBuildImpossible(): self
    {
        return new self('Cannot read current project, frontend assets will not be built');
    }
}
