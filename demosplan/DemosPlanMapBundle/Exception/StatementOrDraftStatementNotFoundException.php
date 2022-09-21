<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanMapBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;

class StatementOrDraftStatementNotFoundException extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function createFromId(string $id): self
    {
        return new self("Could neither find Draft Statement nor Statement for ID {$id}");
    }
}
