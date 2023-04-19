<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class TagNotFoundException extends ResourceNotFoundException
{
    public static function createFromId(string $id): TagNotFoundException
    {
        return new self("Tag with ID {$id} was not found.");
    }
}
