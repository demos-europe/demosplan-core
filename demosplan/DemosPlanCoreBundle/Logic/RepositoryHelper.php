<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;

class RepositoryHelper
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param string $entityClass
     *
     * @return CoreRepository
     */
    public function getRepository(string $entityClass): CoreRepository
    {
        return $this->managerRegistry->getRepository($entityClass);
    }
}
