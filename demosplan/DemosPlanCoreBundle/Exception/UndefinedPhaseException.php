<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class UndefinedPhaseException extends Exception
{
    public function __construct($phaseKey)
    {
        parent::__construct("Undefined phase for key: $phaseKey");
    }
}
