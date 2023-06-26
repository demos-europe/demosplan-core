<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Logic;

use Doctrine\ORM\TransactionRequiredException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanProcedureBundle\Repository\ProcedureCategoryRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class ProcedureCategoryService extends CoreService
{
    public function __construct(private readonly ProcedureCategoryRepository $procedureCategoryRepository)
    {
    }

    public function getProcedureCategories(): array
    {
        return $this->procedureCategoryRepository->findAll();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function transformProcedureCategoryIdsToObjects(array $procedureCategoriesIds): array
    {
        $array = [];
        foreach ($procedureCategoriesIds as $procedureCategoryId) {
            $array[] = $this->procedureCategoryRepository->get($procedureCategoryId);
        }

        return $array;
    }
}
