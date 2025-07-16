<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\NoResultException;
use Exception;

/**
 * @template-extends CoreRepository<Address>
 */
class AddressRepository extends CoreRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Address
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
     * Add Entity to database.
     *
     * @return Address
     *
     * @throws Exception
     */
    public function add(array $data)
    {
        try {
            $em = $this->getEntityManager();

            $address = new Address();
            $address = $this->generateObjectValues($address, $data);
            $em->persist($address);
            $em->flush();

            return $address;
        } catch (Exception $e) {
            $this->logger->warning('Address could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return Address
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
            $this->logger->warning('Update Address failed Reason: ', [$e]);
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
            $em->remove($em->getReference(Address::class, $entityId));
            $em->flush();
        } catch (Exception $e) {
            $this->logger->warning('Update Address failed Reason: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Set Objectvalues by array.
     *
     * @param Address $entity
     *
     * @return Address
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('city', $data)) {
            $entity->setCity($data['city']);
        }
        if (array_key_exists('code', $data)) {
            $entity->setCode($data['code']);
        }
        if (array_key_exists('email', $data)) {
            $entity->setEmail($data['email']);
        }
        if (array_key_exists('fax', $data)) {
            $entity->setFax($data['fax']);
        }
        if (array_key_exists('phone', $data)) {
            $entity->setPhone($data['phone']);
        }
        if (array_key_exists('postalcode', $data)) {
            $entity->setPostalcode($data['postalcode']);
        }
        if (array_key_exists('postofficebox', $data)) {
            $entity->setPostofficebox($data['postofficebox']);
        }
        if (array_key_exists('region', $data)) {
            $entity->setRegion($data['region']);
        }
        if (array_key_exists('state', $data)) {
            $entity->setState($data['state']);
        }
        if (array_key_exists('street', $data)) {
            $entity->setStreet($data['street']);
        }
        if (array_key_exists('street1', $data)) {
            $entity->setStreet1($data['street1']);
        }
        if (array_key_exists('houseNumber', $data)) {
            $entity->setHouseNumber($data['houseNumber']);
        }
        if (array_key_exists('url', $data)) {
            $entity->setUrl($data['url']);
        }

        // ## Boolean
        $entity->setDeleted(false);
        if (array_key_exists('deleted', $data) && $data['deleted']) {
            $entity->setDeleted(true);
        }

        return $entity;
    }
}
