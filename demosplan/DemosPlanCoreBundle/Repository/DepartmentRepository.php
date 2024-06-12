<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<Department>
 */
class DepartmentRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Department|null
     */
    public function get($entityId)
    {
        try {
            /* @var $department Department */
            $department = $this->findOneBy(['id' => $entityId]);

            return $department;
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @return Department
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();

            $department = new Department();
            $department = $this->generateObjectValues($department, $data);
            $em->persist($department);
            $em->flush();

            return $department;
        } catch (Exception $e) {
            $this->logger->warning('Department could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return Department
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();
            $entity = $this->get($entityId);
            // this is where the magical mapping happens
            $entity = $this->generateObjectValues($entity, $data);
            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Update Department failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Adds a User to an Department.
     *
     * @param string $entityId
     *
     * @return Department
     *
     * @throws Exception
     */
    public function addUser($entityId, User $user)
    {
        try {
            $em = $this->getEntityManager();
            $departmentEntity = $this->get($entityId);
            // add User
            $departmentEntity->addUser($user);
            $em->persist($departmentEntity);
            $em->flush();

            return $departmentEntity;
        } catch (Exception $e) {
            $this->logger->warning(
                'Add User to Department failed Reason: ', [$e]
            );
            throw $e;
        }
    }

    /**
     * Removes a User from a Department.
     *
     * @param string $entityId
     *
     * @return Department
     *
     * @throws Exception
     */
    public function removeUser($entityId, User $user)
    {
        try {
            $em = $this->getEntityManager();
            $entity = $this->get($entityId);
            $entity->removeUser($user);
            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Remove User from Department failed Reason: ', [$e]);
            throw $e;
        }
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
        try {
            $em = $this->getEntityManager();
            $em->remove($em->getReference(Department::class, $entityId));
            $em->flush();

            $this->logger->info('Department deleted: '.$entityId);

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Departnemt failed Reason: ', [$e]);

            return false;
        }
    }

    /**
     * Set Objectvalues by array.
     *
     * @param Department $entity
     *
     * @return Department
     */
    public function generateObjectValues($entity, array $data)
    {
        $commonEntityFields = collect(['code', 'gwId']);
        $this->setEntityFieldsOnFieldCollection($commonEntityFields, $entity, $data);

        if (array_key_exists('name', $data)) {
            $entity->setName($data['name']);
        }

        // ## Boolean
        if (array_key_exists('deleted', $data)) {
            $entity->setDeleted(true);
        } else {
            $entity->setDeleted(false);
        }

        // ## Address
        if (array_key_exists('address', $data)) {
            $entity->addAddress($data['address']);
        }

        // ## Organisation
        if (array_key_exists('organisation', $data) && $data['organisation'] instanceof Orga) {
            $entity->addOrga($data['organisation']);
        }

        return $entity;
    }

    /**
     * Overrides all relevant data field of the given department with default values.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function wipe(Department $department): Department
    {
        $em = $this->getEntityManager();

        $department->setName('');
        $department->setCode(null);
        $department->setGwId(null);

        $department->setDeleted(true);

        $em->persist($department);
        $em->flush();

        return $department;
    }

    /**
     * @param Department $department
     *
     * @return Department
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($department)
    {
        $this->getEntityManager()->persist($department);
        $this->getEntityManager()->flush();

        return $department;
    }
}
