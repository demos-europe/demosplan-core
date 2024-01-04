<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use Exception;
use Symfony\Component\HttpFoundation\File\File;

interface VirusCheckInterface
{
    /**
     * Scan a specific file for a virus.
     *
     * @throws Exception
     */
    public function hasVirus(File $file): bool;
}
