<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<AddressBookEntry>
 */
class AddressBookEntryRepository extends CoreRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return AddressBookEntry|null
     */
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['id' => $entityId]);
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @param AddressBookEntry $addressBookEntry
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($addressBookEntry): AddressBookEntry
    {
        $em = $this->getEntityManager();
        $em->persist($addressBookEntry);
        $em->flush();

        return $addressBookEntry;
    }

    /**
     * Update Object.
     *
     * @param CoreEntity $entity
     *
     * @throws Exception
     */
    public function updateObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Delete Entity.
     *
     * @param string|AddressBookEntry $entity
     *
     * @throws EntityNotFoundException
     */
    public function delete($entity): bool
    {
        $toDelete = $entity instanceof AddressBookEntry ? $entity : $this->get($entity);

        if (!$toDelete instanceof AddressBookEntry) {
            $this->logger->warning('Delete AddressBookEntry failed: Given ID not found.');
            throw new EntityNotFoundException('Delete AddressBookEntry failed: Given ID not found.');
        }

        return $this->deleteObject($toDelete);
    }

    /**
     * Delete Entity.
     *
     * @param AddressBookEntry $toDelete
     *
     * @throws EntityNotFoundException
     */
    public function deleteObject($toDelete): bool
    {
        try {
            $this->getEntityManager()->remove($toDelete);
            $this->getEntityManager()->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->error('Delete AddressBookEntry failed: ', [$e]);
        }

        return false;
    }
}
