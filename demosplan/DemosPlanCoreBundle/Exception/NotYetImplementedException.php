<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use LogicException;

class NotYetImplementedException extends LogicException
{
    public static function wip()
    {
        return new self('This method is a work in progress');
    }
}
