<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class ProcedureCoupleTokenAlreadyUsedException extends DemosException
{
    public static function createFromTokenValue(string $tokenValue): ProcedureCoupleTokenAlreadyUsedException
    {
        return new self("Procedure couple token with the value {$tokenValue} was already used to couple procedures.");
    }
}
