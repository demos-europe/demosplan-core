<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanUserBundle\Repository\AddressBookEntryRepository;
use demosplan\DemosPlanUserBundle\ValueObject\AddressBookEntryVO;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;

class AddressBookEntryService extends CoreService
{
    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    /**
     * @var SortMethodFactory
     */
    private $sortMethodFactory;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var AddressBookEntryRepository
     */
    private $addressBookEntryRepository;

    public function __construct(AddressBookEntryRepository $addressBookEntryRepository, DqlConditionFactory $conditionFactory, EntityFetcher $entityFetcher, MessageBagInterface $messageBag, SortMethodFactory $sortMethodFactory)
    {
        $this->addressBookEntryRepository = $addressBookEntryRepository;
        $this->conditionFactory = $conditionFactory;
        $this->entityFetcher = $entityFetcher;
        $this->messageBag = $messageBag;
        $this->sortMethodFactory = $sortMethodFactory;
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
            return $this->entityFetcher->listEntitiesUnrestricted(
                AddressBookEntry::class,
                [$this->conditionFactory->propertyHasAnyOfValues($addressBookEntryIds, 'id')],
                [$this->sortMethodFactory->propertyAscending(['name'])]
            );
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
