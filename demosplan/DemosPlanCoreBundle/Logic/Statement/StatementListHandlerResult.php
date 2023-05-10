<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;

class StatementListHandlerResult
{
    /**
     * @var array
     */
    protected $activeFilters;

    /**
     * @var ToBy
     */
    protected $activeSort;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var string|bool
     */
    protected $manuallySorted;

    /**
     * @var array
     */
    protected $sort;

    /**
     * @var array
     */
    protected $statementList;

    public function __construct(
        $statementList,
        $filters,
        $sort,
        ToBy $activeSort,
        $manuallySorted,
        $activeFilters
    ) {
        $this->statementList = $statementList;
        $this->filters = $filters;
        $this->sort = $sort;
        $this->activeSort = $activeSort;
        $this->manuallySorted = $manuallySorted;
        $this->activeFilters = $activeFilters;
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
