<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Traits\RepositoryLegacyShizzle;
use Exception;

class HashedQueryRepository extends CoreRepository
{
    use RepositoryLegacyShizzle;

    /**
     * @return HashedQuery
     */
    public function addObject(HashedQuery $hashedQuery)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($hashedQuery);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not add new filterSet: ', [$e]);
        }

        return $hashedQuery;
    }

    public function updateObject(HashedQuery $hashedQuery)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($hashedQuery);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not update filterSet: ', [$e]);
        }
    }

    public function deleteObject(HashedQuery $hashedQuery)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->remove($hashedQuery);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not delete FilterSet '.$hashedQuery->getId().': ', [$e]);
        }
    }
}
