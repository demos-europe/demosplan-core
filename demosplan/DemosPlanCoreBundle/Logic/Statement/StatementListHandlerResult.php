<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;

class StatementListHandlerResult
{
    /**
     * @var ToBy
     */
    protected $activeSort;

    /**
     * @param mixed[] $activeFilters
     * @param mixed[] $filters
     * @param string|bool $manuallySorted
     * @param mixed[] $sort
     * @param mixed[] $statementList
     */
    public function __construct(
        protected $statementList,
        protected $filters,
        protected $sort,
        ToBy $activeSort,
        protected $manuallySorted,
        protected $activeFilters
    ) {
        $this->activeSort = $activeSort;
    }

    public function getActiveFilters(): array
    {
        return $this->activeFilters;
    }

    public function getActiveSort(): array
    {
        return $this->activeSort->toArray();
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getManuallySorted()
    {
        return $this->manuallySorted;
    }

    public function getSort(): array
    {
        return $this->sort;
    }

    public function getStatementList(): array
    {
        return $this->statementList;
    }

    public function setStatementList(array $statementList): self
    {
        $this->statementList = $statementList;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'statementlist'  => $this->statementList,
            'filters'        => $this->filters,
            'sort'           => $this->sort,
            'activeSort'     => $this->activeSort->toArray(),
            'manuallySorted' => $this->manuallySorted,
            'activeFilters'  => $this->activeFilters,
        ];
    }
}
