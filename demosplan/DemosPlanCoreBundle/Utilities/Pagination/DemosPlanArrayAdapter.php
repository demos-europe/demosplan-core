<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utilities\Pagination;

use Pagerfanta\Adapter\ArrayAdapter;
use Traversable;

class DemosPlanArrayAdapter extends ArrayAdapter
{
    /** @var int */
    protected $nbResults = 0;

    public function setNbResults(int $resultCount)
    {
        $this->nbResults = $resultCount;
    }

    /**
     * @return int
     */
    public function getNbResults(): int
    {
        return $this->nbResults;
    }

    /**
     * @param int $offset
     * @param int $length
     *
     * @return array|Traversable
     */
    public function getSlice($offset, $length): iterable
    {
        return $this->getArray();
    }
}
