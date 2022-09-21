<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;

class OrgaNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): OrgaNotFoundException
    {
        return new self("Orga with ID {$id} was not found.");
    }
}
