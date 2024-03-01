<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiPaginationInterface;

class APIPagination extends ValueObject implements ApiPaginationInterface
{
    /**
     * Number of items on a page.
     */
    protected int $size = 0;

    /**
     * Page number.
     */
    protected int $number = 0;
    protected string $sortBy = '';
    protected string $sortDirection = '';

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
        if (str_starts_with($sortString, '-')) {
            $sortDirection = 'desc';
            // strip descending marker from sortString;
            $sortString = substr($sortString, 1);
        }
        $this->setSortDirection($sortDirection);
        $this->setSortBy($sortString);
    }

    public function getSort()
    {
        if ('' === $this->getSortBy() || '' === $this->getSortDirection()) {
            return null;
        }

        return ToBy::createArray($this->getSortBy(), $this->getSortDirection());
    }

    public function getSize(): int
    {
        $this->checkIfLocked();

        return $this->size;
    }

    public function getNumber(): int
    {
        $this->checkIfLocked();

        return $this->number;
    }

    public function getSortBy(): string
    {
        $this->checkIfLocked();

        return $this->sortBy;
    }

    public function getSortDirection(): string
    {
        $this->checkIfLocked();

        return $this->sortDirection;
    }

    public function setSize(int $size): ApiPaginationInterface
    {
        $this->verifySettability('size');
        $this->size = $size;

        return $this;
    }

    public function setNumber(int $number): ApiPaginationInterface
    {
        $this->verifySettability('number');
        $this->number = $number;

        return $this;
    }

    public function setSortBy(string $sortBy): ApiPaginationInterface
    {
        $this->verifySettability('sortBy');
        $this->sortBy = $sortBy;

        return $this;
    }

    public function setSortDirection(string $sortDirection): ApiPaginationInterface
    {
        $this->verifySettability('sortDirection');
        $this->sortDirection = $sortDirection;

        return $this;
    }
}
