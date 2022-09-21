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

class ResourceNotFoundException extends Exception
{
    /**
     * @return static
     */
    public static function createResourceNotFoundException(string $typeName, string $id): self
    {
        return new self("No resource available for the type {$typeName} and ID {$id}");
    }
}
