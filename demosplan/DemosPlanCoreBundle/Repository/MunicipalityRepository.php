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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends FluentRepository<Municipality>
 */
class MunicipalityRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Municipality
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get municipality failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return Municipality
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!array_key_exists('name', $data)) {
                throw new InvalidArgumentException('Trying to add a Municipality without Name');
            }

            $municipality = $this->generateObjectValues(new Municipality(), $data);
            $em->persist($municipality);
            $em->flush();

            return $municipality;
        } catch (Exception $e) {
            $this->logger->warning('Create Municipality failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param Municipality $municipality
     *
     * @return Municipality
     *
     * @throws Exception
     */
    public function addObject($municipality)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($municipality);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add municipality failed: ', [$e]);
        }

        return $municipality;
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
     * @param Municipality $municipality
     *
     * @return Municipality
     */
    public function updateObject($municipality)
    {
        try {
            $this->getEntityManager()->persist($municipality);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update municipality failed: ', [$e]);

            return false;
        }

        return $municipality;
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
        $toDelete = $this->find($entityId);

        return $this->delete($toDelete);
    }

    /**
     * Delete Entity.
     *
     * @param Municipality $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (is_null($toDelete)) {
            $this->logger->warning(
                'Delete municipality failed: Given ID not found.'
            );
            throw new EntityNotFoundException('Delete municipality failed: Given ID not found.');
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete municipality failed: ', [$e]);
        }

        return false;
    }

    /**
     * Get all Entities.
     *
     * @return Municipality[]
     */
    public function getAll()
    {
        try {
            $manager = $this->getEntityManager();
            $query = $manager->createQueryBuilder()
                ->select('municipality')
                ->from(Municipality::class, 'municipality')
                ->orderBy('municipality.name', 'ASC')
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
     * @param Municipality $entity
     *
     * @return Municipality
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('name', $data)) {
            $entity->setName($data['name']);
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
