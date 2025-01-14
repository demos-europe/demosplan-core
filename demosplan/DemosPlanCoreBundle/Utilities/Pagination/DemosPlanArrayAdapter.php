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

class DemosPlanArrayAdapter extends ArrayAdapter
{
    /** @var int */
    protected $nbResults = 0;

    /**
     * The constructor is needed as parent constructor defines array variable as private
     * and we need to access it here.
     */
    public function __construct(
        private readonly array $array,
    ) {
        parent::__construct($array);
    }

    public function setNbResults(int $resultCount)
    {
        $this->nbResults = $resultCount;
    }

    public function getNbResults(): int
    {
        return $this->nbResults;
    }

    public function getSlice(int $offset, int $length): iterable
    {
        return $this->array;
    }
}
