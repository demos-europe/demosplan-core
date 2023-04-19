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

class UnknownIdsException extends Exception
{
    /** @var array|null */
    protected $expectedIds;
    /** @var array|null */
    protected $foundIds;

    /**
     * @return array|null
     */
    public function getFoundIds()
    {
        return $this->foundIds;
    }

    public function setFoundIds(array $foundIds)
    {
        $this->foundIds = $foundIds;
    }

    /**
     * @return array|null
     */
    public function getExpectedIds()
    {
        return $this->expectedIds;
    }

    public function setExpectedIds(array $expectedIds)
    {
        $this->expectedIds = $expectedIds;
    }
}
