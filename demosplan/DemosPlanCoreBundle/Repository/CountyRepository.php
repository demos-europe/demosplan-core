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
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use InvalidArgumentException;

/**
 * @template-extends FluentRepository<County>
 */
class CountyRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return County|null
     */
    public function get($entityId)
    {
        try {
            return $this->find($entityId);
        } catch (Exception $e) {
            $this->logger->warning('Get county failed: ', [$e]);

            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return County
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();
            if (!\array_key_exists('name', $data)) {
                throw new InvalidArgumentException('Trying to add a County without Name');
            }

            $county = $this->generateObjectValues(new County(), $data);
            $em->persist($county);
            $em->flush();

            return $county;
        } catch (Exception $e) {
            $this->logger->warning('Create County failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entity to database.
     *
     * @param County $county
     *
     * @return County
     *
     * @throws Exception
     */
    public function addObject($county)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($county);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Add county failed: ', [$e]);
        }

        return $county;
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return County
     *
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function update($entityId, array $data)
    {
        $county = $this->get($entityId);
        $county = $this->generateObjectValues($county, $data);
        $em = $this->getEntityManager();
        $em->persist($county);
        $em->flush();

        return $county;
    }

    /**
     * Update Entity.
     *
     * @param County $county
     *
     * @return County|false
     */
    public function updateObject($county)
    {
        try {
            $this->getEntityManager()->persist($county);
            $this->getEntityManager()->flush();
        } catch (Exception $e) {
            $this->logger->error('Update county failed: ', [$e]);

            return false;
        }

        return $county;
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
     * @param County $toDelete
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function delete($toDelete)
    {
        if (null === $toDelete) {
            $this->logger->warning('Delete county failed: Given ID not found.');
            throw new EntityNotFoundException();
        }
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete county failed: ', [$e]);
        }

        return false;
    }

    /**
     * Get all Entities.
     *
     * @return County[]
     *
     * @deprecated left for old migrations only, you most probably want to use {@link CountyRepository::getAllOfCustomer} instead
     */
    public function getAll()
    {
        try {
            $manager = $this->getEntityManager();
            $query = $manager->createQueryBuilder()
                ->select('county')
                ->from(County::class, 'county')
                ->orderBy('county.name', 'ASC')
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
     * @param County $entity
     *
     * @return County
     */
    public function generateObjectValues($entity, array $data)
    {
        if (\array_key_exists('name', $data)) {
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

    /**
     * @return array<int, County>
     */
    public function getAllOfCustomer(Customer $customer): array
    {
        $queryBuilder = $this->createQueryBuilder('countiesPerCustomer')
            ->from(County::class, 'county')
            ->join('county.customerCounties', 'customerCounties')
            ->where('customerCounties.customer = :customer')
            ->setParameter('customer', $customer->getId())
            ->orderBy('countiesPerCustomer.name', 'ASC');

        return $queryBuilder->getQuery()->getResult();
    }
}
