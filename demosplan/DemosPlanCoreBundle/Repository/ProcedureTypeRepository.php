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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends CoreRepository<ProcedureType>
 */
class ProcedureTypeRepository extends CoreRepository implements ObjectInterface
{
    /**
     * @param ProcedureType $procedureType
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteObject($procedureType): void
    {
        $this->getEntityManager()->remove($procedureType);
        $this->getEntityManager()->flush();
    }

    public function get($procedureTypeId): ProcedureType
    {
        return $this->find($procedureTypeId);
    }

    /**
     * @param ProcedureType $procedureType
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($procedureType): ProcedureType
    {
        $em = $this->getEntityManager();
        $em->persist($procedureType);
        $em->flush();

        return $procedureType;
    }

    /**
     * @param ProcedureType $entity
     *
     * @return void
     */
    public function updateObject($entity)
    {
        // No need to update yet
    }

    public function delete($entityId)
    {
        // No need to delete yet
        return false;
    }
}
