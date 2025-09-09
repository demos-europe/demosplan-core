<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

use function in_array;

class Search
{
    /**
     * @var string|null
     */
    protected $searchTerm;

    /**
     * @var array<string, SearchField[]>
     */
    protected $configuredFields;

    /**
     * @var array<int, string>
     */
    protected $excludedFields = [];

    /**
     * @var string[]
     */
    protected $scopes = [AbstractQuery::SCOPE_EXTERNAL];

    /**
     * @var array<int,string>|null
     */
    protected $targetFields;

    /**
     * @param array<string, SearchField[]> $configuredFields all fields that were set in the configuration
     */
    public function __construct(array $configuredFields)
    {
        $this->configuredFields = $configuredFields;
    }

    public function getSearchTerm(): ?string
    {
        return $this->searchTerm;
    }

    /**
     * In case of searchTerm is an empty string, the search will be executed anyway
     * and find anything.
     *
     * @param string $searchTerm
     */
    public function setSearchTerm($searchTerm): self
    {
        // colons and slashes must be masked to avoid shard failures
        $search = str_replace([':', '/'], ['\:', '\/'], $searchTerm);
        $this->searchTerm = trim($search);

        return $this;
    }

    /**
     * Gets all fields that are allowed to be searched according to this instance.
     *
     * For a field to be allowed, the following things must be true:
     *
     * 1. The field must be defined for searching in either the {@link AbstractQuery::SCOPE_ALL} or
     *    in one of the scopes set in this instance's {@link Search::$scopes}.
     * 2. If {@link Search::$targetFields} was set in this instance, then it must contain the
     *    {@link SearchField::getName() name} of the field.
     * 3. The {@link Search::$excludedFields} of this instance must not contain
     *    {@link SearchField::getField()} of the field.
     *
     * @return SearchField[]
     */
    public function getAvailableFields(): array
    {
        $defaultFields = in_array(AbstractQuery::SCOPE_ALL, $this->scopes)
            ? []
            : $this->getAvailableFieldsScope(AbstractQuery::SCOPE_ALL);
        $availableFieldsPerScope = array_map($this->getAvailableFieldsScope(...), $this->scopes);
        $availableFields = array_merge($defaultFields, ...$availableFieldsPerScope);

        return array_filter($availableFields, fn(SearchField $searchField): bool => !in_array($searchField->getField(), $this->excludedFields));
    }

    /**
     * @return array<int, SearchField>
     */
    private function getAvailableFieldsScope(string $scope): array
    {
        $configuredFieldsInScope = $this->configuredFields[$scope] ?? [];

        if (null === $this->targetFields) {
            return $configuredFieldsInScope;
        }

        return array_filter($configuredFieldsInScope, $this->isFieldAllowed(...));
    }

    /**
     * @param array<int,string> $names
     */
    public function limitFieldsByNames(array $names): void
    {
        $this->targetFields = $names;
    }

    private function isFieldAllowed(SearchField $searchField): bool
    {
        return in_array($searchField->getName(), $this->targetFields, true);
    }

    /**
     * @param string[] $scopes
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Get Array of Search field to be used in Elasticsearch Search definition.
     *
     * @return array
     */
    public function getFieldsArray()
    {
        $fieldArray = [];
        // use default fields if nothing is set
        $fields = $this->getAvailableFields();

        foreach ($fields as $field) {
            $fieldArray[] = $field->getField().'^'.$field->getBoost();
        }

        return $fieldArray;
    }
}
