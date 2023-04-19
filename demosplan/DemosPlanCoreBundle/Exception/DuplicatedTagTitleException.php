<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class DuplicatedTagTitleException extends Exception
{
    /**
     * @return static
     */
    public static function createFromTitleAndProcedureId(string $tagTitle, string $procedureId): self
    {
        return new self("A tag with the title {$tagTitle} already exist in a procedure with the ID {$procedureId}");
    }
}
