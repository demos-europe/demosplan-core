<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureCategoryRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;

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
