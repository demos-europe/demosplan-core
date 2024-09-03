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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends FluentRepository<ProcedureBehaviorDefinition>
 */
class ProcedureBehaviorDefinitionRepository extends FluentRepository implements ObjectInterface
{
    /**
     * @param ProcedureBehaviorDefinition $procedureBehaviorDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($procedureBehaviorDefinition): ProcedureBehaviorDefinition
    {
        $em = $this->getEntityManager();
        $em->persist($procedureBehaviorDefinition);
        $em->flush();

        return $procedureBehaviorDefinition;
    }

    /**
     * @param ProcedureBehaviorDefinition $procedureBehaviorDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteObject($procedureBehaviorDefinition): void
    {
        $this->getEntityManager()->remove($procedureBehaviorDefinition);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string $procedureBehaviorDefinitionId
     */
    public function get($procedureBehaviorDefinitionId): ?ProcedureBehaviorDefinition
    {
        return $this->find($procedureBehaviorDefinitionId);
    }

    /**
     * @param ProcedureBehaviorDefinition $procedureBehaviorDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($procedureBehaviorDefinition): ProcedureBehaviorDefinition
    {
        $this->getEntityManager()->persist($procedureBehaviorDefinition);
        $this->getEntityManager()->flush();

        return $procedureBehaviorDefinition;
    }

    /**
     * @param string $procedureBehaviorDefinitionId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($procedureBehaviorDefinitionId): void
    {
        $this->deleteObject($this->find($procedureBehaviorDefinitionId));
    }
}
