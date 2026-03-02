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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @template-extends CoreRepository<ProcedurePhaseDefinition>
 *
 * @method ProcedurePhaseDefinition|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProcedurePhaseDefinition|null findOneBy(array $criteria, array $orderBy = null)
 * @method ProcedurePhaseDefinition[]    findAll()
 * @method ProcedurePhaseDefinition[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProcedurePhaseDefinitionRepository extends CoreRepository
{
    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getMaxOrderForCustomerAndAudience(string $customerId, string $audience): int
    {
        $result = $this->getEntityManager()
            ->createQuery(
                'SELECT MAX(p.orderInAudience) FROM '.ProcedurePhaseDefinition::class.' p
                 WHERE p.customer = :customerId AND p.audience = :audience'
            )
            ->setParameter('customerId', $customerId)
            ->setParameter('audience', $audience)
            ->getSingleScalarResult();

        return $result ?? -1;
    }
}
