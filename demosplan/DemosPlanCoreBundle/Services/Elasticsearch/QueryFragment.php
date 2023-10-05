<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

use demosplan\DemosPlanCoreBundle\Exception\InvalidElasticsearchQueryConfigurationException;

/**
 * Query Statements.
 */
class QueryFragment extends AbstractQuery
{
    /** @var bool Should versions of a fragment be included */
    protected $includeVersions = false;

    public function getEntity(): string
    {
        return 'statementFragment';
    }

    /**
     * @throws InvalidElasticsearchQueryConfigurationException
     */
    protected function isValidConfiguration(array $queryDefinition): bool
    {
        // set definition from config
        if (!array_key_exists('statementFragment', $queryDefinition)) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        if (
            !array_key_exists('external', $queryDefinition['statementFragment']['sort_default'])
            || !array_key_exists('filter', $queryDefinition['statementFragment'])
            || !array_key_exists('search', $queryDefinition['statementFragment'])
            || !array_key_exists('sort', $queryDefinition['statementFragment'])
            || !array_key_exists('sort_default', $queryDefinition['statementFragment'])
            || !array_key_exists('internal', $queryDefinition['statementFragment']['sort_default'])
            || !array_key_exists('external', $queryDefinition['statementFragment']['sort_default'])
        ) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function shouldIncludeVersions()
    {
        return $this->includeVersions;
    }

    /**
     * @param bool $includeVersions
     */
    public function setIncludeVersions($includeVersions)
    {
        $this->includeVersions = $includeVersions;
    }
}
