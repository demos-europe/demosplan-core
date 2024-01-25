<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\Exception\ValueObjectException;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

class DraftStatementListFilters extends ValueObject
{
    protected $organisationId = '';
    protected $departmentId = '';
    protected $elementsId = '';
    protected $searchWord = '';
    protected $sortBy = '';
    protected $sortDirection = '';
    protected $scope = '';

    public function getOrganisationId(): string
    {
        return $this->organisationId;
    }

    public function setOrganisationId(string $organisationId)
    {
        $this->organisationId = $organisationId;
    }

    public function getDepartmentId(): string
    {
        return $this->departmentId;
    }

    public function setDepartmentId(string $departmentId)
    {
        $this->departmentId = $departmentId;
    }

    public function getElementsId(): string
    {
        return $this->elementsId;
    }

    public function setElementsId(string $elementsId)
    {
        $this->elementsId = $elementsId;
    }

    public function getSearchWord(): string
    {
        return $this->searchWord;
    }

    public function setSearchWord(string $searchWord)
    {
        $this->searchWord = $searchWord;
    }

    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    public function setSortBy(string $sortBy)
    {
        $this->sortBy = $sortBy;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function setSortDirection(string $sortDirection)
    {
        $this->sortDirection = $sortDirection;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function setScope(string $scope)
    {
        $this->scope = $scope;
    }

    /**
     * Ignore that ValueObject is not locked.
     * We need to modify on filter change.
     */
    protected function getProperty(?string $name)
    {
        if (!property_exists($this, $name)) {
            throw new ValueObjectException('Property '.$name.' does not exist!');
        }

        return $this->{$name};
    }

    public function get($name)
    {
        $fieldMapping = $this->getFieldMapping();
        $property = $fieldMapping[$name] ?? null;

        return $this->getProperty($property);
    }

    public function has($name): bool
    {
        return array_key_exists($name, $this->getFieldMapping());
    }

    protected function getFieldMapping()
    {
        return [
            'f_organisation' => 'organisationId',
            'f_document'     => 'elementsId',
            'f_department'   => 'departmentId',
            'f_scope'        => 'scope',
            'f_sort'         => 'sortBy',
            'f_sort_ascdesc' => 'sortDirection',
            'search_word'    => 'searchWord',
        ];
    }
}
