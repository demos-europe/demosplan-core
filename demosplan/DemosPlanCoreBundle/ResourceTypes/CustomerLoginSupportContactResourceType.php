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
use DemosEurope\DemosplanAddon\EntityPath\Paths;
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
 * @property-read End                   $eMailAddress
 * @property-read CustomerResourceType     $customer
 */
class CustomerLoginSupportContactResourceType extends DplanResourceType
{
    public function __construct(
        protected readonly EmailAddressRepository $emailAddressRepository,
        protected readonly SupportContactRepository $supportContactRepository
    ) {
    }

    protected function getProperties(): array
    {
        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        Assert::notNull($currentCustomerId);
        $customerCondition = $this->conditionFactory->propertyHasValue($currentCustomerId, Paths::supportContact()->customer);

        $properties = [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->title)->readable()->initializable()->updatable([$customerCondition]),
            $this->createAttribute($this->phoneNumber)->readable()->initializable()->updatable([$customerCondition]),
            $this->createAttribute($this->text)->readable()->initializable()->updatable([$customerCondition]),
            $this->createAttribute($this->eMailAddress)
                ->readable()
                ->initializable()
                ->updatable([$customerCondition]),
        ];

        if ($this->resourceTypeStore->getCustomerResourceType()->isReferencable()) {
            $properties[] = $this->createToOneRelationship($this->customer)->filterable();
        }

        return $properties;
    }

    protected function getAccessConditions(): array
    {
        $currentCustomerId = $this->currentCustomerService->getCurrentCustomer()->getId();
        if (null === $currentCustomerId) {
            return [$this->conditionFactory->false()];
        }

        return [
            // A CustomerLoginContact is only a CustomerLoginContact if it is connected to a customer
            $this->conditionFactory->propertyIsNotNull($this->customer),
            // and if its supportType is this type explicitly.
            $this->conditionFactory->propertyHasValue(
                SupportContact::SUPPORT_CONTACT_TYPE_CUSTOMER_LOGIN,
                $this->supportType
            ),
            // Additionally, we limit the access to contacts of the current customer
            $this->conditionFactory->propertyHasValue($currentCustomerId, $this->customer->id),
            // the visibility has no meaning in regard to CustomerLoginSupportContacts - its default is true
            $this->conditionFactory->propertyHasValue(true, $this->visible),
        ];
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
        return 'CustomerLoginSupportContact';
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
                        SupportContact::SUPPORT_CONTACT_TYPE_CUSTOMER_LOGIN,
                        $attributes[$this->title->getAsNamesInDotNotation()],
                        $attributes[$this->phoneNumber->getAsNamesInDotNotation()],
                        $attributes[$this->eMailAddress->getAsNamesInDotNotation()],
                        $attributes[$this->text->getAsNamesInDotNotation()],
                        $currentCustomer,
                        true,
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

    public function isCreatable(): bool
    {
        return $this->hasManagementPermission();
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $entity = $this->getEntity($entityIdentifier);
                $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
                Assert::same($entity->getCustomer(), $currentCustomer);
                $currentCustomer->getContacts()->removeElement($entity);
                $this->resourceTypeService->validateObject($currentCustomer);

                parent::deleteEntity($entityIdentifier);
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_customer_support_contact_administration');
    }

    public function isUpdateAllowed(): bool
    {
        return null !== $this->currentCustomerService->getCurrentCustomer()->getId() && $this->hasManagementPermission();
    }

    protected function hasManagementPermission(): bool
    {
        return $this->currentUser->hasPermission('feature_customer_login_support_contact_administration');
    }
}
