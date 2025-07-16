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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Traits\RepositoryLegacyShizzle;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends FluentRepository<UserFilterSet>
 */
class UserFilterSetRepository extends FluentRepository
{
    use RepositoryLegacyShizzle;

    /**
     * @param string $entityId
     *
     * @return UserFilterSet|null
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @param UserFilterSet $entity
     *
     * @return bool
     */
    public function addObject($entity)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($entity);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not add new filterSet: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param UserFilterSet $entity
     *
     * @return bool
     */
    public function updateObject($entity)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->persist($entity);
            $entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not update filterSet: ', [$e]);

            return false;
        }

        return false;
    }

    /**
     * @param UserFilterSet $userFilterSet
     *
     * @return bool - true if successfully deleted the given entity, otherwise false
     */
    public function deleteObject($userFilterSet)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->remove($userFilterSet);
            $entityManager->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Could not delete UserFilterSet', ['id' => $userFilterSet->getId(), 'exception' => $e]);
        }

        return false;
    }
}
