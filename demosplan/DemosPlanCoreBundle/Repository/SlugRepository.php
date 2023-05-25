<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Exception;

class SlugRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Slug
     */
    public function get($entityId)
    {
        return $this->getEntityManager()
            ->getRepository(Slug::class)
            ->findOneBy(['id' => $entityId]);
    }

    public function addObject($entity)
    {
        try {
            $manager = $this->getEntityManager();
            $manager->persist($entity);
            $manager->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Add SlugObject failed Message: ', [$e]);
            throw $e;
        }
    }

    public function updateObject($entity)
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId)
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param Slug $entity
     *
     * @return bool
     */
    public function deleteObject($entity)
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
