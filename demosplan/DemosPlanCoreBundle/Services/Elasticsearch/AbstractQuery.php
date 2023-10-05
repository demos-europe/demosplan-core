<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidElasticsearchQueryConfigurationException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use Elastica\Query\AbstractQuery as AbstractQueryES;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;
use function in_array;
use function is_array;

abstract class AbstractQuery
{
    public const MUST = 'must';
    public const MUSTNOT = 'mustNot';
    public const SHOULD = 'should';
    public const SCOPE_ALL = 'all';
    public const SCOPE_INTERNAL = 'internal';
    public const SCOPE_EXTERNAL = 'external';
    public const SCOPE_PLANNER = 'planner';
    public const MUST_QUERY = 'mustQuery';
    public const MUST_NOT_QUERY = 'mustNotQuery';

    /**
     * @var array<string, FilterDisplay[]>
     */
    protected $configuredFilters = [];

    /**
     * Array of arrays. The 2nd level arrays are identified by string keys and contain Filter instances.
     *
     * @var FilterInterface[][]
     */
    protected $filters = [];

    /**
     * @var int
     */
    protected $limit = 0;

    /**
     * @var Sort[]
     */
    protected $sort = [];

    /**
     * @var array<string, Sort[]>
     */
    protected $configuredSorts;

    /**
     * @var array<string, Sort[]>
     */
    protected $configuredDefaultSorts;

    /**
     * @var string
     */
    protected $entity;
    /**
     * Scopes of the query which will be used in elasticsearch
     * definition. may be e.g. internal|external.
     *
     * @var string[]
     */
    protected $scopes = [];

    /**
     * @var Search|null
     */
    protected $search;

    /**
     * @var Search
     */
    protected $configuredSearch;

    /** @var GlobalConfigInterface */
    protected $globalConfig;
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @throws UserNotFoundException
     */
    public function __construct(GlobalConfigInterface $globalConfig, TranslatorInterface $translator, private readonly CurrentUserInterface $currentUser)
    {
        $this->globalConfig = $globalConfig;
        $this->translator = $translator;

        $queryDefinition = $globalConfig->getElasticsearchQueryDefinition();
        $this->isValidConfiguration($queryDefinition);
        $entityDefinition = $queryDefinition[$this->getEntity()];

        $this->configuredSearch = $this->loadSearchConfiguration($entityDefinition['search']);
        $this->configuredSorts = $this->loadSortConfiguration($entityDefinition['sort']);
        $this->configuredDefaultSorts = $this->loadDefaultSortsConfiguration($entityDefinition['sort_default']);
        $this->configuredFilters = $this->loadFilterConfiguration($entityDefinition['filter']);
        $this->setInitialScopes();
    }

    /**
     * @return array<string, Sort[]>
     */
    private function loadSortConfiguration(array $sortEntityDefintion): array
    {
        $configuredSorts = [];
        foreach ($sortEntityDefintion as $scope => $sortTypeDefinition) {
            foreach ($sortTypeDefinition as $sortName => $sortDefinition) {
                $sort = new Sort($sortName);
                $sort->setTitleKey($sortDefinition['titleKey']);
                foreach ($sortDefinition['fields'] as $fieldName => $direction) {
                    $sort->addField(new SortField($fieldName, $direction));
                }
                $configuredSorts[$scope][] = $sort;

                if (array_key_exists('permission', $sortDefinition)) {
                    $sort->setPermission($sortDefinition['permission']);
                }
            }
        }

        return $configuredSorts;
    }

    /**
     * @return array<string, Sort[]>
     */
    private function loadDefaultSortsConfiguration(array $defaultSortEntityDefinition): array
    {
        $configuredDefaultSorts = [];
        foreach ($defaultSortEntityDefinition as $scope => $sortDefaultTypeDefinition) {
            $defaultSort = new Sort($sortDefaultTypeDefinition['field'] ?? 'default');
            $defaultSort->addField(
                new SortField(
                    $sortDefaultTypeDefinition['field'] ?? '',
                    $sortDefaultTypeDefinition['direction'] ?? ''
                )
            );
            $configuredDefaultSorts[$scope] = $defaultSort;
        }

        return $configuredDefaultSorts;
    }

    private function loadSearchConfiguration(array $searchEntityDefinition): Search
    {
        $configuredFields = [];
        foreach ($searchEntityDefinition as $scope => $searchTypeDefinition) {
            $configuredFields[$scope] = [];
            foreach ($searchTypeDefinition as $searchName => $searchDefinition) {
                // $searchDefinition may not be set at all
                $searchDefinition ??= [];
                $titleKey = $searchDefinition['titleKey'] ?? '';
                $boost = $searchDefinition['boost'] ?? 1;

                $configuredFields[$scope][] = new SearchField(
                    $searchName,
                    $searchDefinition['field'] ?? $searchName,
                    $titleKey,
                    $boost
                );
            }
        }

        return new Search($configuredFields);
    }

    /**
     * @return array<string, FilterDisplay[]>
     */
    private function loadFilterConfiguration(array $filterEntityDefinition): array
    {
        $availableFilters = [];
        foreach ($filterEntityDefinition as $scope => $filterTypeDefinition) {
            foreach ($filterTypeDefinition as $filterName => $filterDefinition) {
                $filter = new FilterDisplay($filterName);
                $filter->setTitleKey($filterDefinition['titleKey'] ?? '');
                $filter->setField($filterDefinition['field'] ?? $filterName);
                $filter->setDisplayInInterface($filterDefinition['display'] ?? true);
                $filter->setAggregationField(
                    $filterDefinition['aggregation']['field'] ?? null
                );
                $filter->setAggregationNullValue(
                    $filterDefinition['aggregation']['nullValue'] ?? null
                );

                if (array_key_exists('permission', $filterDefinition)) {
                    $filter->setPermission($filterDefinition['permission']);
                }
                if (array_key_exists('hasNoAssignmentOption', $filterDefinition)) {
                    $filter->setHasNoAssignmentSelectOption($filterDefinition['hasNoAssignmentOption']);
                }
                if (array_key_exists('contextHelpKey', $filterDefinition)) {
                    $filter->setContextHelpKey($filterDefinition['contextHelpKey']);
                }
                $availableFilters[$scope][] = $filter;
            }
        }

        return $availableFilters;
    }

    /**
     * Set initial Scopes based on defined roles. May be overridden if
     * business logic needs it.
     *
     * @throws UserNotFoundException
     */
    protected function setInitialScopes(): void
    {
        if ($this->currentUser->getUser()->isPublicAgency()) {
            // reset scopes
            $this->setScopes([self::SCOPE_INTERNAL]);
        }

        // user is planner
        if ($this->currentUser->getUser()->isPlanner()) {
            // add planner scope
            $this->addScope(self::SCOPE_PLANNER);
        }
    }

    abstract public function getEntity(): string;

    /**
     * @return FilterDisplay[]
     */
    public function getAvailableFilters(): array
    {
        $configuredFilters = in_array(self::SCOPE_ALL, $this->scopes)
            ? []
            : $this->configuredFilters[self::SCOPE_ALL] ?? [];

        // Get available filters by scope.
        $scopedFilters = array_map(fn (string $scope): array => $this->configuredFilters[$scope] ?? [], $this->scopes);

        return array_merge($configuredFilters, ...$scopedFilters);
    }

    /**
     * get only Fields to be used in interface.
     *
     * @return FilterDisplay[]
     */
    public function getInterfaceFilters(): array
    {
        $availableFilters = [];
        if (array_key_exists('all', $this->configuredFilters)) {
            /** @var FilterDisplay $defaultField */
            foreach ($this->configuredFilters['all'] as $defaultField) {
                if (false === $defaultField->isDisplayInInterface()) {
                    continue;
                }
                $availableFilters[] = $defaultField;
            }
        }

        foreach ($this->getScopes() as $scope) {
            $availableFilters = $this->getInterfaceFiltersScope($scope, $availableFilters);
        }

        return $availableFilters;
    }

    /**
     * get only Fields to be used in interface.
     *
     * @param string $scope
     * @param array  $availableFilters
     *
     * @return FilterDisplay[]
     */
    public function getInterfaceFiltersScope($scope, $availableFilters = []): array
    {
        if (!array_key_exists($scope, $this->configuredFilters)) {
            return $availableFilters;
        }
        /** @var FilterDisplay $scopeField */
        foreach ($this->configuredFilters[$scope] as $scopeField) {
            if (false === $scopeField->isDisplayInInterface()) {
                continue;
            }
            $availableFilters[] = $scopeField;
        }

        return $availableFilters;
    }

    /**
     * Append Available Filter.
     *
     * @param FilterDisplay $availableFilter
     * @param string        $scope
     *
     * @deprecated exposure not desired; needed for test only
     */
    public function addAvailableFilter($availableFilter, $scope = 'all'): void
    {
        $this->configuredFilters[$scope][] = $availableFilter;
    }

    /**
     * @return FilterInterface[][] Array of arrays. The 2nd level arrays are identified by
     *                             string keys and contain Filter instances.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Checks whether Filter has any valid value.
     *
     * @param array|string $filter
     */
    public function isFilterValueEmpty($filter): bool
    {
        if (is_string($filter)) {
            return '' === $filter || null === $filter;
        }
        if (is_array($filter)) {
            $filters = collect($filter)->reject(static fn ($filterEntry) => '' === $filterEntry || null === $filterEntry);

            return 0 === $filters->count();
        }

        return false;
    }

    /**
     * Returns true if the 'key' property value of the given $filterValue is identical to the 'field' property value of
     * one of the instances in the must filters array (getFiltersMust) AND the 'value' property of the given
     * $filterValue is identical to the 'value' property of the same matching instance in the must filter array.
     *
     * @param FilterValue $filterValue the FilterValue instance to check
     *
     * @return bool true if the described condition occurs, false otherwise
     */
    public function isFilterValueSelected(FilterValue $filterValue): bool
    {
        foreach ($this->getFiltersMust() as $filter) {
            if ($filter instanceof FilterMissing) {
                return false;
            }
            if ($filterValue->getKey() === $filter->getField() && $filterValue->getValue() === $filter->getValue()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Filter[]
     */
    public function getFilterMustQueries(): array
    {
        if (!array_key_exists(self::MUST_QUERY, $this->filters)
            || null === $this->filters[self::MUST_QUERY]) {
            return [];
        }

        return $this->filters[self::MUST_QUERY];
    }

    /**
     * @return Filter[]
     */
    public function getFilterMustNotQueries(): array
    {
        if (!array_key_exists(self::MUST_NOT_QUERY, $this->filters)
            || null === $this->filters[self::MUST_NOT_QUERY]) {
            return [];
        }

        return $this->filters[self::MUST_NOT_QUERY];
    }

    public function addFilterMustQuery(AbstractQueryES $query): void
    {
        if (!array_key_exists(self::MUST_QUERY, $this->filters)
            || null === $this->filters[self::MUST_QUERY]) {
            $this->filters[self::MUST_QUERY] = [];
        }

        $this->filters[self::MUST_QUERY][] = $query;
    }

    public function addFilterMustNotQuery(AbstractQueryES $query): void
    {
        if (!array_key_exists(self::MUST_NOT_QUERY, $this->filters)
            || null === $this->filters[self::MUST_NOT_QUERY]) {
            $this->filters[self::MUST_NOT_QUERY] = [];
        }

        $this->filters[self::MUST_NOT_QUERY][] = $query;
    }

    /**
     * @return Filter[]
     */
    public function getFiltersMust(): array
    {
        if (!array_key_exists(self::MUST, $this->filters) || null === $this->filters[self::MUST]) {
            return [];
        }

        return $this->filters[self::MUST];
    }

    /**
     * @return Filter[]
     */
    public function getFiltersMustNot(): array
    {
        if (!array_key_exists(self::MUSTNOT, $this->filters) || null === $this->filters[self::MUSTNOT]) {
            return [];
        }

        return $this->filters[self::MUSTNOT];
    }

    /**
     * @return Filter[]
     */
    public function getFiltersShould(): array
    {
        if (!array_key_exists(self::SHOULD, $this->filters) || null === $this->filters[self::SHOULD]) {
            return [];
        }

        return $this->filters[self::SHOULD];
    }

    /**
     * @param Filter[] $filters
     */
    public function setFilters($filters): void
    {
        $this->filters = $filters;
    }

    /**
     * Append filtervalue.
     *
     * @param string $fieldName
     */
    public function addFilterMust($fieldName, $value): AbstractQuery
    {
        $filter = new Filter($fieldName, $value);

        return $this->addFilterObject($filter, self::MUST);
    }

    /**
     * Remove a must filter by name.
     *
     * @param string $fieldName
     *
     * @return $this
     */
    public function removeFilterMust($fieldName): self
    {
        return $this->removeFilter($fieldName, self::MUST);
    }

    /**
     * Append filtervalue.
     *
     * @param string $fieldName
     */
    public function addFilterShould($fieldName, $value): AbstractQuery
    {
        $filter = new Filter($fieldName, $value);

        return $this->addFilterObject($filter, self::SHOULD);
    }

    /**
     * Remove a must filter by name.
     *
     * @param string $fieldName
     *
     * @return $this
     */
    public function removeFilterShould($fieldName): self
    {
        return $this->removeFilter($fieldName, self::SHOULD);
    }

    /**
     * Append missing filter.
     *
     * @param string $fieldName
     */
    public function addFilterMustMissing($fieldName): AbstractQuery
    {
        $filter = new FilterMissing($fieldName);

        return $this->addFilterObject($filter, self::MUST);
    }

    /**
     * Append prefix filter.
     *
     * @param string $fieldName
     * @param string $value
     */
    public function addFilterMustPrefix($fieldName, $value): AbstractQuery
    {
        $filter = new FilterPrefix($fieldName, $value);

        return $this->addFilterObject($filter, self::MUST);
    }

    /**
     * Remove a filter by name.
     *
     * @param string $fieldName
     * @param string $type
     *
     * @return $this
     */
    public function removeFilter($fieldName, $type): self
    {
        if (array_key_exists($type, $this->filters)) {
            $this->filters[$type] = collect($this->filters[$type])
                // remove Filter
                ->filter(fn ($filter) =>
                    /* @var FilterInterface $filter */
                    $fieldName !== $filter->getField())
                // reset keys
                ->values()
                // convert to array
                ->toArray();
        }

        return $this;
    }

    /**
     * Append filter.
     */
    public function addFilterObject(FilterInterface $filter, string $type): AbstractQuery
    {
        $this->filters[$type][] = $filter;

        return $this;
    }

    /**
     * Append filtervalue.
     */
    public function addFilterMustNot(string $fieldName, $value): AbstractQuery
    {
        $filter = new Filter($fieldName, $value);

        return $this->addFilterObject($filter, self::MUSTNOT);
    }

    /**
     * Remove a mustNot filter by name.
     *
     * @return $this
     */
    public function removeFilterMustNot(string $fieldName): self
    {
        return $this->removeFilter($fieldName, self::MUSTNOT);
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): AbstractQuery
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return Sort[]
     */
    public function getSort(): array
    {
        if (0 === count($this->sort)) {
            $sortDefault = $this->getSortDefault();

            return null === $sortDefault ? [] : [$sortDefault];
        }

        return $this->sort;
    }

    /**
     * @param Sort[]|Sort $sorts
     */
    public function setSort($sorts): AbstractQuery
    {
        $sorts = !is_array($sorts) ? [$sorts] : $sorts;
        foreach ($sorts as $sort) {
            if (!$sort instanceof Sort) {
                throw new InvalidArgumentException('Must be a Sort array, there is, at least one '.$sort::class.' element');
            }
        }

        $this->sort = $sorts;

        return $this;
    }

    /**
     * Append Sort.
     *
     * @param Sort|null $sort
     */
    public function addSort($sort): AbstractQuery
    {
        if (!$sort instanceof Sort) {
            throw new InvalidArgumentException('Must be a Sort object');
        }

        $this->sort[] = $sort;

        return $this;
    }

    /**
     * Gets default sort depending on scope.
     */
    public function getSortDefault(): ?Sort
    {
        if (0 === count($this->getScopes())) {
            $this->setScopes([self::SCOPE_EXTERNAL]);
        }

        // @todo define which is the correct DefaultSort when multiple Scopes are set
        // use first atm
        foreach ($this->getScopes() as $scope) {
            if (array_key_exists($scope, $this->configuredDefaultSorts)) {
                return $this->configuredDefaultSorts[$scope];
            }
        }

        return null;
    }

    /**
     * @return Sort[]
     */
    public function getAvailableSorts(): array
    {
        $availableSorts = [];

        if (null != $this->configuredSorts) {
            foreach ($this->configuredSorts[self::SCOPE_ALL] as $defaultField) {
                $availableSorts[] = $defaultField;
            }

            foreach ($this->getScopes() as $scope) {
                $availableSorts = $this->getAvailableSortsScope($scope, $availableSorts);
            }
        }

        // display sorts only once
        return collect($availableSorts)->unique(static function (Sort $item) {
            $uniqueHash = '';
            foreach ($item->getFields() as $field) {
                $uniqueHash .= $field->getName();
            }

            return $uniqueHash;
        })->values()->all();
    }

    /**
     * @param string $scope
     * @param array  $availableSorts
     *
     * @return Sort[]
     */
    private function getAvailableSortsScope($scope, $availableSorts = []): array
    {
        if (!array_key_exists($scope, $this->configuredSorts)) {
            return $availableSorts;
        }

        foreach ($this->configuredSorts[$scope] as $scopeField) {
            $availableSorts[] = $scopeField;
        }

        return $availableSorts;
    }

    /**
     * Get available Sort by name.
     *
     * @param string $name
     */
    public function getAvailableSort($name): ?Sort
    {
        foreach ($this->getAvailableSorts() as $sort) {
            if ($name == $sort->getName()) {
                return $sort;
            }
        }

        return null;
    }

    public function getSearch(): ?Search
    {
        return $this->search;
    }

    public function setSearch(Search $search): AbstractQuery
    {
        $this->search = $search;

        return $this;
    }

    public function getAvailableSearch(): Search
    {
        return $this->configuredSearch;
    }

    /**
     * This method must be implemented for each QueryObject (which represents an entity).
     *
     * @throws InvalidElasticsearchQueryConfigurationException
     */
    abstract protected function isValidConfiguration(array $queryDefinition): bool;

    /**
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param string $scope
     */
    public function hasScope($scope): bool
    {
        return collect($this->scopes)->contains($scope);
    }

    public function setScope(?string $scope): AbstractQuery
    {
        return $this->setScopes(null === $scope ? [] : [$scope]);
    }

    /**
     * @param string $scope
     */
    public function addScope($scope): self
    {
        $scopes = collect($this->scopes);
        if (!$scopes->contains($scope)) {
            $scopes->push($scope);
        }
        $this->setScopes($scopes->toArray());

        return $this;
    }

    /**
     * @param string[] $scopes
     */
    public function setScopes(array $scopes): AbstractQuery
    {
        $this->scopes = $scopes;

        // set search scope because searchfields differ depending on scope
        $this->getAvailableSearch()->setScopes($scopes);

        return $this;
    }
}
