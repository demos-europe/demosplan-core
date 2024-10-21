<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class UndefinedPhaseException extends Exception
{
    public function __construct($phaseKey)
    {
        parent::__construct("Undefined phase for key: $phaseKey");
    }
}
