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
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends FluentRepository<PriorityArea>
 */
class PriorityAreaRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return PriorityArea
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get priorityArea failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return PriorityArea
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('key', $data)) {
                throw new InvalidArgumentException('Trying to add a PriorityArea without key');
            }

            $priorityArea = $this->generateObjectValues(new PriorityArea(), $data);
            $em->persist($priorityArea);
            $em->flush();

            return $priorityArea;
        } catch (Exception $e) {
            $this->logger->warning('Create PriorityArea failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param PriorityArea $priorityArea
     *
     * @return PriorityArea
     *
     * @throws Exception
     */
    public function addObject($priorityArea)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($priorityArea);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add priorityArea failed: ', [$e]);
        }

        return $priorityArea;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     */
    public function update($entityId, array $data): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Update Entity.
     *
     * @param PriorityArea $priorityArea
     *
     * @return PriorityArea
     */
    public function updateObject($priorityArea)
    {
        try {
            $this->getEntityManager()->persist($priorityArea);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update priorityArea failed: ', [$e]);

            return false;
        }

        return $priorityArea;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function deleteById($entityId)
    {
        /** @var PriorityArea $toDelete */
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Delete Entity.
     *
     * @param PriorityArea $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning('Delete priorityArea failed: Given ID not found.');
            throw new EntityNotFoundException('Delete priorityArea failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete priorityArea failed: ', [$e]);
        }

        return false;
    }

    /**
     * Get all Entities.
     *
     * @return PriorityArea[]
     */
    public function getAll()
    {
        try {
            $manager = $this->getEntityManager();
            $query = $manager->createQueryBuilder()
                ->select('priority_area')
                ->from(PriorityArea::class, 'priority_area')
                ->orderBy('priority_area.key', 'ASC')
                ->getQuery();

            return $query->getResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param PriorityArea $entity
     *
     * @return PriorityArea
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('key', $data)) {
            $entity->setKey($data['key']);
        }
        if (array_key_exists('type', $data)) {
            $entity->setType($data['type']);
        }

        return $entity;
    }

    /**
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }
}
