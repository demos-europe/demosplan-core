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
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
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
     * Returns all phase definitions for the given customer, ordered by audience and orderInAudience.
     * Falls back to global definitions (customer IS NULL) if no customer-specific definitions exist.
     *
     * @return ProcedurePhaseDefinition[]
     */
    public function findByCustomerOrderedByAudience(Customer $customer): array
    {
        $results = $this->findBy(['customer' => $customer], ['audience' => 'ASC', 'orderInAudience' => 'ASC']);

        if ([] === $results) {
            $results = $this->findBy(['customer' => null], ['audience' => 'ASC', 'orderInAudience' => 'ASC']);
        }

        return $results;
    }

    /**
     * Finds the evaluating phase definition (permissionSet=read, participationState=finished)
     * for the given customer and audience. Falls back to global (customer=null) definitions.
     */
    public function findEvaluatingDefinition(string $audience, ?Customer $customer): ?ProcedurePhaseDefinition
    {
        $criteria = ['audience' => $audience, 'permissionSet' => 'read', 'participationState' => 'finished'];

        if (null !== $customer) {
            $result = $this->findOneBy(array_merge($criteria, ['customer' => $customer]));
            if (null !== $result) {
                return $result;
            }
        }

        return $this->findOneBy(array_merge($criteria, ['customer' => null]));
    }

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
