<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Common;

use Doctrine\ORM\QueryBuilder;

/**
 * Shared by `QueryCollectionExtensionInterface` implementations that need to restrict an API
 * Platform-built `QueryBuilder` using EDT access conditions, which can't be applied to that
 * QueryBuilder directly.
 */
trait DoctrineAccessConditionSubqueryTrait
{
    /**
     * Restricts $queryBuilder to rows whose id is also returned by $subQueryBuilder (typically built
     * from EDT access conditions via a repository's `generateAccessConditionQueryBuilder()`), by
     * applying it as a subquery: `<rootAlias>.id IN (<subquery DQL>)`.
     *
     * Subquery parameters are merged into the outer query. This is safe as long as other filters on
     * the outer query continue using named parameters; positional parameter usage could require
     * renumbering to avoid collisions.
     */
    private function restrictToSubqueryIds(QueryBuilder $queryBuilder, QueryBuilder $subQueryBuilder): void
    {
        $subAlias = $subQueryBuilder->getRootAliases()[0];
        $subQueryBuilder->select("$subAlias.id");

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere($queryBuilder->expr()->in("$rootAlias.id", $subQueryBuilder->getDQL()));

        foreach ($subQueryBuilder->getParameters() as $parameter) {
            $queryBuilder->setParameter($parameter->getName(), $parameter->getValue(), $parameter->getType());
        }
    }
}
