<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

use DemosEurope\DemosplanAddon\Contracts\Exceptions\DuplicateInternIdExceptionImterface;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;

class DuplicateInternIdException extends DemosException implements DuplicateInternIdExceptionImterface
{
    public static function create(string $internId, string $procedureId): self
    {
        return new self("The internId {$internId} already exists in a procedure with the ID {$procedureId}");
    }
}
