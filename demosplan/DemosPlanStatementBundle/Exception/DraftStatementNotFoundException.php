<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;

class DraftStatementNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): DraftStatementNotFoundException
    {
        return new self("DraftStatement with the ID ${id} was not found.");
    }
}
