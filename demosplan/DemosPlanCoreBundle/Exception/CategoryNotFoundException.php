<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class CategoryNotFoundException extends ResourceNotFoundException
{
    public static function createFromName(string $name): CategoryNotFoundException
    {
        return new self("Category with name {$name} was not found.");
    }
}
