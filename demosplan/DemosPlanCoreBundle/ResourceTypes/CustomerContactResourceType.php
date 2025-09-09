<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceCreateFlushEvent;
use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use demosplan\DemosPlanCoreBundle\Repository\SupportContactRepository;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\PathBuilding\End;
use EDT\Wrapping\CreationDataInterface;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<SupportContact>
 *
 * @property-read End                      $supportType
 * @property-read End                      $title
 * @property-read End                      $phoneNumber
 * @property-read End                      $text
 * @property-read End                      $visible
 * @property-read End                      $eMailAddress
 * @property-read CustomerResourceType     $customer
 */
class CustomerContactResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly EmailAddressRepository $emailAddressRepository,
        protected readonly SupportContactRepository $supportContactRepository
    ) {
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->title)->readable()->updatable()->initializable(),
            $this->createAttribute($this->phoneNumber)->readable()->updatable()->initializable(),
            $this->createAttribute($this->text)->readable()->updatable()->initializable(),
            $this->createAttribute($this->eMailAddress)
                ->readable()
                ->updatable()
                ->initializable(),
        ];

        if ($this->hasManagementPermission()) {
            $properties[] = $this->createAttribute($this->visible)
                ->readable()
                ->updatable()
                ->filterable()
                ->initializable();
        }

        return $properties;
    }

    protected function getAccessConditions(): array
    {
        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        if (null === $currentCustomerId) {
            return [$this->conditionFactory->false()];
        }

        $conditions = [
            // A CustomerContact is only a CustomerContact if it is connected to a customer
            $this->conditionFactory->propertyIsNotNull($this->customer),
            // and if its supportType is of this type explicitly
            $this->conditionFactory->propertyHasValue(SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT, $this->supportType),
            // Additionally, we limit the access to contacts of the current customer
            $this->conditionFactory->propertyHasValue($currentCustomerId, $this->customer->id),
        ];

        if (!$this->hasManagementPermission()) {
            // Users without management permission can access all visible CustomerContacts,
            // regardless of customer.
            $conditions[] = $this->conditionFactory->propertyHasValue(true, $this->visible);
        }

        return $conditions;
    }

    public function getEntityClass(): string
    {
        return SupportContact::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public static function getName(): string
    {
        return 'CustomerContact';
    }

    public function createEntity(CreationDataInterface $entityData): ModifiedEntity
    {
        try {
            return $this->getTransactionService()->executeAndFlushInTransaction(
                function () use ($entityData): ModifiedEntity {
                    $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
                    $attributes = $entityData->getAttributes();

                    // create support contact
                    $contact = new SupportContact(
                        SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT,
                        $attributes[$this->title->getAsNamesInDotNotation()],
                        $attributes[$this->phoneNumber->getAsNamesInDotNotation()],
                        $attributes[$this->eMailAddress->getAsNamesInDotNotation()],
                        $attributes[$this->text->getAsNamesInDotNotation()],
                        $currentCustomer,
                        $attributes[$this->visible->getAsNamesInDotNotation()],
                    );

                    // update customer
                    $currentCustomer->getContacts()->add($contact);

                    // validate entities
                    $this->resourceTypeService->validateObject($contact);
                    $this->resourceTypeService->validateObject($currentCustomer);

                    // persist created entities
                    $this->supportContactRepository->persistEntities([$contact]);

                    $this->eventDispatcher->dispatch(new BeforeResourceCreateFlushEvent($this, $contact));

                    return new ModifiedEntity($contact, []);
                }
            );
        } catch (Exception $exception) {
            $this->addCreationErrorMessage([]);

            throw $exception;
        }
    }

    public function isCreateAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier) {
                $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
                $entity = $this->getEntity($entityIdentifier);
                Assert::same($entity->getCustomer(), $currentCustomer);
                $currentCustomer->getContacts()->removeElement($entity);
                $this->resourceTypeService->validateObject($currentCustomer);

                parent::deleteEntity($entityIdentifier);
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return $this->hasManagementPermission();
    }

    protected function hasManagementPermission(): bool
    {
        return $this->currentUser->hasPermission('feature_customer_support_contact_administration');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->hasManagementPermission();
    }
}
