<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Procedure;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getLabel()
 * @method void   setLabel(string $label)
 * @method string getValue()
 * @method void   setValue(string $projection)
 */
class AvailableProjectionVO extends ValueObject
{
    protected string $label;
    protected string $value;
}
