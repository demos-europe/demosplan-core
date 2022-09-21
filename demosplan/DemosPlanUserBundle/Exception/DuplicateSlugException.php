<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class DuplicateSlugException extends InvalidArgumentException
{
    private $duplicatedSlug;

    /**
     * @return mixed
     */
    public function getDuplicatedSlug()
    {
        return $this->duplicatedSlug;
    }

    /**
     * @param mixed $duplicatedSlug
     */
    public function setDuplicatedSlug($duplicatedSlug)
    {
        $this->duplicatedSlug = $duplicatedSlug;
    }
}
