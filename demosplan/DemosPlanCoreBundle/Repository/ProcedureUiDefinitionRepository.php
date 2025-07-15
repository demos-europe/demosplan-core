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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends FluentRepository<ProcedureUiDefinition>
 */
class ProcedureUiDefinitionRepository extends FluentRepository implements ObjectInterface
{
    /**
     * @param ProcedureUiDefinition $procedureUiDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($procedureUiDefinition): ProcedureUiDefinition
    {
        $em = $this->getEntityManager();
        $em->persist($procedureUiDefinition);
        $em->flush();

        return $procedureUiDefinition;
    }

    /**
     * @param ProcedureUiDefinition $procedureUiDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteObject($procedureUiDefinition): void
    {
        $this->getEntityManager()->remove($procedureUiDefinition);
        $this->getEntityManager()->flush();
    }

    /**
     * @param string $procedureUiDefinitionId
     */
    public function get($procedureUiDefinitionId): ?ProcedureUiDefinition
    {
        return $this->find($procedureUiDefinitionId);
    }

    /**
     * @param ProcedureUiDefinition $procedureUiDefinition
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($procedureUiDefinition): ProcedureUiDefinition
    {
        $this->getEntityManager()->persist($procedureUiDefinition);
        $this->getEntityManager()->flush();

        return $procedureUiDefinition;
    }

    /**
     * @param string $procedureUiDefinitionId
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($procedureUiDefinitionId): void
    {
        $this->deleteObject($this->find($procedureUiDefinitionId));
    }
}
