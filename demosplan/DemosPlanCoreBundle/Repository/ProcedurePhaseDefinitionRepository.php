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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseDefinitionInterface;
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
     * Returns phase definitions for the given customer, ordered by audience and orderInAudience.
     * Falls back to global definitions (customer IS NULL) if no customer-specific definitions exist.
     * Pass $excludeDeleted = false to include soft-deleted definitions (e.g. for name resolution).
     *
     * @return ProcedurePhaseDefinition[]
     */
    public function findByCustomerOrderedByAudience(CustomerInterface $customer, bool $excludeDeleted = true): array
    {
        $criteria = ['customer' => $customer];
        if ($excludeDeleted) {
            $criteria['isDeleted'] = false;
        }
        $results = $this->findBy($criteria, ['audience' => 'ASC', 'orderInAudience' => 'ASC']);

        if ([] === $results) {
            $results = $this->findBy(['customer' => null], ['audience' => 'ASC', 'orderInAudience' => 'ASC']);
        }

        return $results;
    }

    /**
     * Finds the evaluating phase definition (permissionSet=read, participationState=finished)
     * for the given customer and audience. Falls back to global (customer=null) definitions.
     * When multiple matches exist, returns the one with the lowest orderInAudience.
     */
    public function findEvaluatingDefinition(string $audience, ?CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        $criteria = ['audience' => $audience, 'permissionSet' => 'read', 'participationState' => 'finished'];
        $orderBy = ['orderInAudience' => 'ASC'];

        if ($customer instanceof CustomerInterface) {
            $result = $this->findOneBy(array_merge($criteria, ['customer' => $customer]), $orderBy);
            if (null !== $result) {
                return $result;
            }
        }

        return $this->findOneBy(array_merge($criteria, ['customer' => null]), $orderBy);
    }

    /**
     * Finds the first phase definition (lowest orderInAudience) for the given audience and customer.
     * Falls back to global (customer=null) definitions.
     */
    public function findInitialDefinition(string $audience, ?CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        if ($customer instanceof CustomerInterface) {
            $result = $this->findOneBy(['audience' => $audience, 'customer' => $customer], ['orderInAudience' => 'ASC']);
            if (null !== $result) {
                return $result;
            }
        }

        return $this->findOneBy(['audience' => $audience, 'customer' => null], ['orderInAudience' => 'ASC']);
    }

    public function findByNameAndAudienceAndCustomer(string $name, string $audience, CustomerInterface $customer): ?ProcedurePhaseDefinition
    {
        return $this->findOneBy(['name' => $name, 'audience' => $audience, 'customer' => $customer, 'isDeleted' => false]);
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

    /**
     * Returns true if any non-deleted procedure currently references this definition
     * as its active or designated phase (internal or external audience).
     */
    public function isReferencedByActiveProcedure(ProcedurePhaseDefinitionInterface $phaseDefinition): bool
    {
        $count = $this->getEntityManager()
            ->createQuery(
                'SELECT COUNT(p.id) FROM '.ProcedureInterface::class.' p
                JOIN p.phase phase
                LEFT JOIN p.publicParticipationPhase pubPhase
                WHERE p.deleted = false
                AND (
                    phase.phaseDefinition = :def
                    OR phase.designatedPhaseDefinition = :def
                    OR pubPhase.phaseDefinition = :def
                    OR pubPhase.designatedPhaseDefinition = :def
                    )'
            )
            ->setParameter('def', $phaseDefinition)
            ->getSingleScalarResult();

        return $count > 0;
    }
}
