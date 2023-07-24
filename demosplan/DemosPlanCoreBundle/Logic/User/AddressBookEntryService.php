<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\AddressBookEntryRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\User\AddressBookEntryVO;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

class AddressBookEntryService extends CoreService
{
    public function __construct(
        private readonly AddressBookEntryRepository $addressBookEntryRepository,
        private readonly MessageBagInterface $messageBag
    ) {
    }

    /**
     * Returns the related object of the given UUID.
     *
     * @param string $addressBookEntryId
     *
     * @return AddressBookEntry|null
     *
     * @throws MessageBagException
     */
    public function getAddressBookEntry($addressBookEntryId)
    {
        $addressBookEntry = $this->addressBookEntryRepository->get($addressBookEntryId);

        if (!$addressBookEntry instanceof AddressBookEntry) {
            $this->messageBag->add('warning', 'warning.addressBookEntry.not.found');
        }

        return $addressBookEntry;
    }

    /**
     * Add a single AddressBookEntry to the AddressBook of the given Organisation.
     *
     * @return addressBookEntry|false - true in case of successfully deleted, otherwise false
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createAddressBookEntry(AddressBookEntryVO $addressBookEntry)
    {
        $addressBookEntryToAdd = $addressBookEntry->generateEntity();

        return $this->addressBookEntryRepository->addObject($addressBookEntryToAdd);
    }

    /**
     * Returns the related objects of the given UUIDs.
     *
     * @param string[] $addressBookEntryIds
     *
     * @return AddressBookEntry[]
     *
     * @throws Exception
     */
    public function getAddressBookEntries(array $addressBookEntryIds)
    {
        try {
            return $this->addressBookEntryRepository->findBy([
                'id' => $addressBookEntryIds,
            ], [
                'name' => Criteria::ASC,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei getOrganisationsByIds Orga: ', [$e]);
            throw $e;
        }
    }

    /**
     * Deletes a specific AddressBookEntry.
     *
     * @return bool - true if Entry was successfully deleted, otherwise false
     *
     * @throws MessageBagException
     */
    public function deleteAddressBookEntry(string $addressBookEntryId): bool
    {
        try {
            $toDelete = $this->getAddressBookEntry($addressBookEntryId);
            if ($toDelete instanceof AddressBookEntry) {
                return $this->addressBookEntryRepository->deleteObject($toDelete);
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Error on removeAddressBookEntry(): ', [$e]);
            $this->messageBag->add('error', 'error.delete.addressBookEntry');
        }

        return false;
    }

    /**
     * @param string[] $addressBookEntryIds
     *
     * @throws MessageBagException
     */
    public function deleteAddressBookEntries($addressBookEntryIds)
    {
        foreach ($addressBookEntryIds as $addressBookEntryId) {
            $this->deleteAddressBookEntry($addressBookEntryId);
        }
    }

    /**
     * Returns all AddressBookEntries of the given Organisation.
     *
     * @return AddressBookEntry[]
     */
    public function getAddressBookEntriesOfOrganisation(string $organisationId): array
    {
        try {
            return $this->addressBookEntryRepository->findBy(['organisation' => $organisationId], ['name' => 'asc']);
        } catch (Exception $e) {
            $this->logger->error('Error on getAddressBookOfOrganisation(): ', [$e]);

            return [];
        }
    }
}
