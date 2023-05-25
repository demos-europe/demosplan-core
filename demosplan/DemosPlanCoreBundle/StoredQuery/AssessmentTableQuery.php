<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StoredQuery;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;

class AssessmentTableQuery extends AbstractStoredQuery
{
    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Extended search.
     *
     * @var array
     */
    protected $searchFields = [];

    /**
     * @var string
     */
    protected $searchWord = '';

    /**
     * @var array
     */
    protected $sorting = [];

    /** @var string */
    protected $procedureId = '';

    /**
     * @var AssessmentTableViewMode
     */
    protected $viewMode;

    public function getFormat(): string
    {
        return 'assessment_table';
    }

    public function fromJson(array $json): void
    {
        $this->filters = $json['filters'];
        $this->searchWord = $json['searchWord'];
        $this->searchFields = $json['searchFields'];
        $this->sorting = $json['sorting'];
        $this->procedureId = $json['procedureId'];
        $this->viewMode = AssessmentTableViewMode::create($json['viewMode']);
    }

    public function toJson(): array
    {
        return [
            'filters'      => $this->filters,
            'searchWord'   => $this->searchWord,
            'searchFields' => $this->searchFields,
            'sorting'      => $this->sorting,
            'procedureId'  => $this->procedureId,
            'viewMode'     => (string) $this->viewMode,
        ];
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
    }

    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    public function setSearchFields(array $searchFields): void
    {
        $this->searchFields = $searchFields;
    }

    public function getSearchWord(): string
    {
        return $this->searchWord;
    }

    public function setSearchWord(string $searchWord): void
    {
        $this->searchWord = $searchWord;
    }

    public function getSorting(): array
    {
        return $this->sorting;
    }

    public function setSorting(array $sorting): void
    {
        $this->sorting = $sorting;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }

    public function getViewMode(): AssessmentTableViewMode
    {
        return $this->viewMode;
    }

    public function setViewMode(AssessmentTableViewMode $viewMode): void
    {
        $this->viewMode = $viewMode;
    }
}
