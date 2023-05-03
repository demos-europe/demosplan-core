<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Repository\AddressRepository;
use Exception;
use Psr\Log\LoggerInterface;

class AddressService
{
    /**
     * @var AddressRepository
     */
    private $addressRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(AddressRepository $addressRepository, LoggerInterface $logger)
    {
        $this->addressRepository = $addressRepository;
        $this->logger = $logger;
    }

    /**
     * Get single Addressobject.
     *
     * @param string $entityId
     *
     * @return Address
     *
     * @throws Exception
     */
    public function getAddress($entityId)
    {
        try {
            return $this->addressRepository->get($entityId);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Adresse:', [$e]);
            throw $e;
        }
    }

    /**
     * delete single Addressobject.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteAddress($entityId)
    {
        try {
            return $this->addressRepository->delete($entityId);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der Adresse: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Addressdata.
     *
     * @param string $addressId
     * @param array  $data
     *
     * @return Address
     *
     * @throws Exception
     */
    public function updateAddress($addressId, $data)
    {
        try {
            return $this->addressRepository->update($addressId, $data);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update der Address: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add Address.
     *
     * @param array $data
     *
     * @return Address
     *
     * @throws Exception
     */
    public function addAddress($data)
    {
        try {
            return $this->addressRepository->add($data);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update der Addresse: ', [$e]);
            throw $e;
        }
    }
}
