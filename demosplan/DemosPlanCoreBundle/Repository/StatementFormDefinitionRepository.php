<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends FluentRepository<StatementFormDefinition>
 */
class StatementFormDefinitionRepository extends FluentRepository implements ObjectInterface
{
    /**
     * @param StatementFormDefinition $statementFormDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($statementFormDefinition): StatementFormDefinition
    {
        $em = $this->getEntityManager();
        $em->persist($statementFormDefinition);
        $em->flush();

        return $statementFormDefinition;
    }

    /**
     * @param StatementFormDefinition $statementFormDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteObject($statementFormDefinition): void
    {
        $this->getEntityManager()->remove($statementFormDefinition);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string $statementFormDefinitionId
     */
    public function get($statementFormDefinitionId): ?StatementFormDefinition
    {
        return $this->find($statementFormDefinitionId);
    }

    /**
     * @param StatementFormDefinition $statementFormDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($statementFormDefinition): StatementFormDefinition
    {
        $this->getEntityManager()->persist($statementFormDefinition);
        $this->getEntityManager()->flush();

        return $statementFormDefinition;
    }

    /**
     * @param string $statementFormDefinitionId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($statementFormDefinitionId): void
    {
        $this->deleteObject($this->find($statementFormDefinitionId));
    }
}
