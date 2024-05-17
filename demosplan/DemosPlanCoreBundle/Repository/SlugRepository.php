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
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Exception;

/**
 * @template-extends FluentRepository<Slug>
 */
class SlugRepository extends FluentRepository implements ObjectInterface
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

    public function updateObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     */
    public function delete($entityId): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param Slug $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
