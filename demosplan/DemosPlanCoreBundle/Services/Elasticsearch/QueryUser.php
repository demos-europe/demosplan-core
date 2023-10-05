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

class QueryUser extends AbstractQuery
{
    public function getEntity(): string
    {
        return 'user';
    }

    protected function isValidConfiguration(array $queryDefinition): bool
    {
        // set definition from config
        if (!array_key_exists('user', $queryDefinition)) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        if (
            !array_key_exists('filter', $queryDefinition['statementFragment'])
            || !array_key_exists('search', $queryDefinition['statementFragment'])
            || !array_key_exists('sort', $queryDefinition['statementFragment'])
            || !array_key_exists('sort_default', $queryDefinition['statementFragment'])
        ) {
            throw new InvalidElasticsearchQueryConfigurationException();
        }

        return true;
    }
}
