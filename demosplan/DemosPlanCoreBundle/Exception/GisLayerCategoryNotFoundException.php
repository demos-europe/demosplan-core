<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class GisLayerCategoryNotFoundException extends ResourceNotFoundException
{
    /**
     * @return static
     */
    public static function createFromId(string $categoryId): self
    {
        return new self("Could not found GisLayerCategory with ID {$categoryId}");
    }
}
