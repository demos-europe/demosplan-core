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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\User\SupportContact;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use EDT\PathBuilding\End;
use Webmozart\Assert\Assert;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<SupportContact>
 * @template-implements CreatableDqlResourceTypeInterface<SupportContact>
 * @template-implements DeletableDqlResourceTypeInterface<SupportContact>
 *
 * @template-extends DplanResourceType<SupportContact>
 *
 * @property-read End                      $title
 * @property-read End                      $phoneNumber
 * @property-read End                      $text
 * @property-read End                      $visible
 * @property-read EmailAddressResourceType $eMailAddress
 * @property-read CustomerResourceType     $customer
 */
class CustomerContactResourceType extends DplanResourceType implements CreatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface, UpdatableDqlResourceTypeInterface
{
    public function __construct(
        protected readonly EmailAddressService $emailAddressService
    ) {
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->title)->readable()->initializable(),
            $this->createAttribute($this->phoneNumber)->readable()->initializable(),
            $this->createAttribute($this->text)->readable()->initializable(),
            $this->createAttribute($this->eMailAddress)->aliasedPath($this->eMailAddress->fullAddress)->readable()->initializable(),
        ];

        if ($this->hasManagementPermission()) {
            $properties[] = $this->createAttribute($this->visible)->readable()->initializable();
        }

        return $properties;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    protected function getAccessConditions(): array
    {
        // A CustomerContact is only a CustomerContact if it is connected to a customer
        $conditions = [
            $this->conditionFactory->propertyIsNotNull($this->customer),
        ];

        $visibilityCondition = $this->conditionFactory->propertyHasValue(true, $this->visible);
        if ($this->hasManagementPermission()) {
            // Users with management permission can access all CustomerContacts in their own customer
            // and all CustomerContacts in other customers that are set visible. I.e. they can **not**
            // access non-visible CustomerContacts of other customers.
            $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
            $conditions[] = $this->conditionFactory->anyConditionApplies(
                $visibilityCondition,
                $this->conditionFactory->propertyHasValue($currentCustomer->getId(), $this->customer->id)
            );
        } else {
            // Users without management permission can access all visible CustomerContacts,
            // regardless of customer.
            $conditions[] = $visibilityCondition;
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

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public static function getName(): string
    {
        return 'CustomerContact';
    }

    public function createObject(array $properties): ResourceChange
    {
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();

        // create/get email address
        $providedEmailAddress = $properties[$this->eMailAddress->getAsNamesInDotNotation()];
        if (null !== $providedEmailAddress) {
            $emailAddressEntity = $this->emailAddressService->getOrCreateEmailAddress($providedEmailAddress);
        } else {
            $emailAddressEntity = null;
        }

        // create support contact
        $contact = new SupportContact(
            $properties[$this->title->getAsNamesInDotNotation()],
            $properties[$this->phoneNumber->getAsNamesInDotNotation()],
            $emailAddressEntity,
            $properties[$this->text->getAsNamesInDotNotation()],
            $currentCustomer,
            $properties[$this->visible->getAsNamesInDotNotation()],
        );

        // update customer
        $currentCustomer->getContacts()->add($contact);

        // validate entities
        $this->resourceTypeService->validateObject($contact);
        $this->resourceTypeService->validateObject($currentCustomer);
        if (null !== $emailAddressEntity) {
            $this->resourceTypeService->validateObject($emailAddressEntity);
        }

        // build resource change
        $change = new ResourceChange($contact, $this, $properties);
        $change->addEntityToPersist($contact);
        if (null !== $emailAddressEntity) {
            $change->addEntityToPersist($emailAddressEntity);
        }

        return $change;
    }

    public function isCreatable(): bool
    {
        return $this->hasManagementPermission();
    }

    /**
     * @param SupportContact $entity
     */
    public function delete(object $entity): ResourceChange
    {
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
        Assert::same($entity->getCustomer(), $currentCustomer);

        $currentCustomer->getContacts()->removeElement($entity);
        $this->resourceTypeService->validateObject($currentCustomer);

        $change = new ResourceChange($entity, $this, []);
        $change->addEntityToDelete($entity);

        return $change;
    }

    public function getRequiredDeletionPermissions(): array
    {
        return ['feature_customer_support_contact_administration'];
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if (!$this->hasManagementPermission()) {
            return [];
        }

        return $this->toProperties(
            $this->title,
            $this->phoneNumber,
            $this->eMailAddress,
            $this->text,
            $this->visible
        );
    }

    /**
     * @param SupportContact $contact
     */
    public function updateObject(object $contact, array $properties): ResourceChange
    {
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
        Assert::same($contact->getCustomer(), $currentCustomer);

        $resourceChange = new ResourceChange($contact, $this, $properties);

        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->title, $contact->setTitle(...));
        $updater->ifPresent($this->phoneNumber, $contact->setPhoneNumber(...));
        $updater->ifPresent(
            $this->eMailAddress,
            function (?string $fullEMailAddress) use ($contact, $resourceChange): void {
                if (null === $fullEMailAddress) {
                    $contact->setEMailAddress(null);
                } else {
                    $emailAddress = $contact->getEMailAddress();
                    if (null === $emailAddress) {
                        $emailAddress = $this->emailAddressService->getOrCreateEmailAddress($fullEMailAddress);
                        $resourceChange->addEntityToPersist($emailAddress);
                        $contact->setEMailAddress($emailAddress);
                    } else {
                        $emailAddress->setFullAddress($fullEMailAddress);
                    }
                    $this->resourceTypeService->validateObject($emailAddress);
                }
            }
        );
        $updater->ifPresent($this->text, $contact->setText(...));
        $updater->ifPresent($this->visible, $contact->setVisible(...));

        $this->resourceTypeService->validateObject($contact);

        return $resourceChange;
    }

    protected function hasManagementPermission(): bool
    {
        return $this->currentUser->hasPermission('feature_customer_support_contact_administration');
    }
}
