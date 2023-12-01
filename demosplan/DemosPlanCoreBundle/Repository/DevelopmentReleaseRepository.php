<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<DevelopmentRelease>
 */
class DevelopmentReleaseRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return CoreEntity
     *
     * @throws EntityNotFoundException
     */
    public function get($entityId)
    {
        $entry = $this->find($entityId);
        if (null === $entry) {
            $this->logger->error('Get release failed: Entry with ID: '.$entityId.' not found.');
            throw new EntityNotFoundException('Get Entry release: Entry with ID: '.$entityId.' not found.');
        }

        return $entry;
    }

    /**
     * @return array
     *
     * @throws EntityNotFoundException
     */
    public function getDevelopmentReleaseList()
    {
        $list = $this->findBy([], ['createDate' => 'ASC']);
        if (null === $list) {
            $this->logger->info('Get List DevelopmentReleaseRepository: nothing found');
            throw new EntityNotFoundException('Nothing found');
        }

        return $list;
    }

    /**
     * Add Entity to database.
     *
     * @return CoreEntity
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function add(array $data)
    {
        if (!array_key_exists('title', $data)) {
            $this->logger->error('Add DevelopmentRelease failed: No title in given array');
            throw new MissingDataException('Add DevelopmentRelease failed: No title in given array');
        }
        if (!array_key_exists('phase', $data)) {
            $this->logger->error('Add DevelopmentRelease failed: No phase in given array');
            throw new MissingDataException('Add DevelopmentRelease failed: No phase in given array');
        }

        $toAdd = $this->generateObjectValues(new DevelopmentRelease(), $data);
        $this->getEntityManager()->persist($toAdd);
        $this->getEntityManager()->flush();

        return $toAdd;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function update($entityId, array $data)
    {
        $toUpdate = $this->find($entityId);
        if (null === $toUpdate) {
            $this->logger->error('Update Release failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Update Release failed: Entry not found.');
        }

        if (array_key_exists('title', $data) && null !== $data['title']) {
            $toUpdate->setTitle($data['title']);
        }

        if (array_key_exists('phase', $data) && null !== $data['phase']) {
            $toUpdate->setPhase($data['phase']);
        }

        if (array_key_exists('description', $data) && null !== $data['description']) {
            $toUpdate->setDescription($data['description']);
        }

        if (array_key_exists('endDate', $data) && null !== $data['endDate']) {
            $date = new DateTime();
            $toUpdate->setEndDate($date->setTimestamp($data['endDate']));
        }

        if (array_key_exists('startDate', $data) && null !== $data['startDate']) {
            $date = new DateTime();
            $toUpdate->setStartDate($date->setTimestamp($data['startDate']));
        }

        $this->getEntityManager()->persist($toUpdate);
        $this->getEntityManager()->flush();

        return $toUpdate;
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete($entityId)
    {
        $toDelete = $this->find($entityId);
        if (null === $toDelete) {
            $this->logger->error('Delete Release failed: Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Delete Release failed: Entry not found.');
        }

        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param DevelopmentRelease $entity
     *
     * @return CoreEntity
     *
     * @throws Exception
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('title', $data)) {
            $entity->setTitle($data['title']);
        }
        if (array_key_exists('phase', $data)) {
            $entity->setPhase($data['phase']);
        }
        if (array_key_exists('startDate', $data)) {
            $date = new DateTime();
            $entity->setStartDate($date->setTimestamp($data['startDate']));
        }
        if (array_key_exists('endDate', $data)) {
            $date = new DateTime();
            $entity->setEndDate($date->setTimestamp($data['endDate']));
        }
        if (array_key_exists('description', $data)) {
            $entity->setDescription($data['description']);
        }

        return $entity;
    }
}
