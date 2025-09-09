<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

/**
 * Query Statements.
 */
class QueryStatement extends AbstractQuery
{
    public function getEntity(): string
    {
        return 'statement';
    }

    protected function isValidConfiguration(array $queryDefinition): bool
    {
        return false;
    }
}
