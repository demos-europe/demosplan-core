<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Class APIPagination.
 *
 * @method int    getSize()
 * @method int    getNumber()
 * @method string getSortBy()
 * @method string getSortDirection()
 * @method self   setSize(int $size)
 * @method self   setNumber(int $number)
 * @method self   setSortBy(string $sortBy)
 * @method self   setSortDirection(string $sortDirection)
 */
class APIPagination extends ValueObject
{
    /**
     * Number of items on a page.
     *
     * @var int
     */
    protected $size;

    /**
     * Page number.
     *
     * @var int
     */
    protected $number;
    protected $sortBy;
    protected $sortDirection;

    /**
     * @param string $sortString
     *
     * @deprecated sorting should be handled independent from pagination, use {@link JsonApiSortingParser}
     */
    public function setSortString($sortString = '')
    {
        if ('' === $sortString || null === $sortString) {
            return;
        }

        $sortDirection = 'asc';
        if (0 === strpos($sortString, '-')) {
            $sortDirection = 'desc';
            // strip descending marker from sortString;
            $sortString = substr($sortString, 1);
        }
        $this->setSortDirection($sortDirection);
        $this->setSortBy($sortString);
    }

    /**
     * @return array|null
     *
     * @deprecated sorting should be handled independent from pagination, use {@link JsonApiSortingParser}
     */
    public function getSort()
    {
        if (null === $this->getSortBy() || null === $this->getSortDirection()) {
            return null;
        }

        return ToBy::createArray($this->getSortBy(), $this->getSortDirection());
    }
}
