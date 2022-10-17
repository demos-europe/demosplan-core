<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\DplanAddon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class DplanAddonRepository extends ServiceEntityRepository
{
    public function persistAndFlush(DplanAddon $dplanAddon)
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($dplanAddon);
        $entityManager->flush();
    }
}
