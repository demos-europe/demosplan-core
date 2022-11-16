<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableObjectInterface;
use demosplan\DemosPlanCoreBundle\Entity\Flood;
use UnexpectedValueException;

class FloodRepository extends CoreRepository implements ImmutableArrayInterface, ImmutableObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     */
    public function get($entityId): ?Flood
    {
        return $this->getEntityManager()
            ->getRepository(Flood::class)
            ->findOneBy(['id' => $entityId]);
    }

    /**
     * @return Flood[]|null
     *
     * @throws UnexpectedValueException
     */
    public function getAllOfIdentifier(?string $identifier, ?string $event): ?array
    {
        return $this->getEntityManager()
            ->getRepository(Flood::class)
            ->findBy(['identifier'      => $identifier,
                           'event'      => $event, ]);
    }

    /**
     * Add Entity to database.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function add(array $data)
    {
        try {
            $entity = new Flood();
            $entity = $this->generateObjectValues($entity, $data);
            $this->addObject($entity);
        } catch (\Exception $e) {
            $this->logger->warning('Create Flood-Entity failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Entityobject to database.
     *
     * @param object $entity
     *
     * @return void
     *
     * @throws \Exception
     */
    public function addObject($entity)
    {
        try {
            $manager = $this->getEntityManager();
            $manager->persist($entity);
            $manager->flush();
        } catch (\Exception $e) {
            $this->logger->warning('Create Flood-Entity failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param $entityId
     *
     * @return bool
     */
    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->delete('FloodControl:Flood', 'f')
                ->andWhere('f.fid = :fid')
                ->setParameter('fid', $entityId);

            $query->getQuery()->execute();
        } catch (\Exception $e) {
            $this->logger->warning('Could not delete Flood-Entry: ', [$e]);
        }
    }

    /**
     * Delete expired flood-entries.
     */
    public function deleteExpired()
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->delete('FloodControl:Flood', 'f')
                ->andWhere('f.expires < :now')
                ->setParameter('now', new \DateTime('NOW'));

            $query->getQuery()->execute();
        } catch (\Exception $e) {
            // do not use monolog as this function is called by maintenance task
            // this may lead to excessive logfile sizes
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param \demosplan\DemosPlanCoreBundle\Entity\Flood $entity
     *
     * @return \demosplan\DemosPlanCoreBundle\Entity\Flood
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }
}
