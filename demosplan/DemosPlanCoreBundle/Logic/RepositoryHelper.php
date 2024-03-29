<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use Doctrine\Persistence\ManagerRegistry;

class RepositoryHelper
{
    public function __construct(private readonly ManagerRegistry $managerRegistry)
    {
    }

    public function getRepository(string $entityClass): CoreRepository
    {
        return $this->managerRegistry->getRepository($entityClass);
    }
}
