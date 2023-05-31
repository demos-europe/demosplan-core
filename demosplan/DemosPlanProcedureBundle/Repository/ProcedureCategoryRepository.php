<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Repository;

use Doctrine\ORM\TransactionRequiredException;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCategory;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class ProcedureCategoryRepository extends CoreRepository
{
    /**
     * Fetch all info about certain ProcedureCategory.
     *
     * @param string $procedureCategoryId
     *
     * @return ProcedureCategory|null
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function get($procedureCategoryId)
    {
        return $this->getEntityManager()->find(ProcedureCategory::class, $procedureCategoryId);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }
}
