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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Traits\RepositoryLegacyShizzle;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends FluentRepository<Bookmark>
 */
class BookmarkRepository extends FluentRepository
{
    use RepositoryLegacyShizzle;

    /**
     * @param string $entityId
     *
     * @return Bookmark|null
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
     * @param Bookmark $entity
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
            $this->logger->error('Could not add new bookmark: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param Bookmark $entity
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
            $this->logger->error('Could not update bookmark: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param Bookmark $bookmark
     *
     * @return bool - true if successfully deleted the given entity, otherwise false
     */
    public function deleteObject($bookmark)
    {
        try {
            $entityManager = $this->getEntityManager();
            $entityManager->remove($bookmark);
            $entityManager->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Could not delete Bookmark', ['id' => $bookmark->getId(), 'exception' => $e]);
        }

        return false;
    }
}
