<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

class FilterHashException extends RuntimeException
{
    /**
     * @param string $field
     */
    public static function missingRequestField($field): self
    {
        return new self("Missing Field: {$field}");
    }
}
