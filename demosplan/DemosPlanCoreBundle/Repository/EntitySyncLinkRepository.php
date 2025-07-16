<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\EntitySyncLink;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;

/**
 * @template-extends FluentRepository<EntitySyncLink>
 */
class EntitySyncLinkRepository extends FluentRepository
{
    /**
     * Retrieves the number of statements that were synchronized from the procedure
     * with the given ID into another procedure.
     *
     * Because only statements that adhere to {@link StatementResourceType} can be
     * synchronized, this method will refrain from adding conditions like "non-original"
     * or "non-deleted" to keep the code complexity low.
     */
    public function getSynchronizedStatementCount(string $procedureId): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('count(statement.id)')
            ->from(Statement::class, 'statement')
            ->innerJoin(EntitySyncLink::class, 'esl', 'WITH', 'statement.id = esl.sourceId')
            ->where('statement.procedure = :procedureId')
            ->andWhere('esl.class = :entityClassName')
            ->setParameter('entityClassName', Statement::class)
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
