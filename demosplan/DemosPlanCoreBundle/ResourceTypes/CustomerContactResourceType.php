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
 * @property-read End                      $supportType
 * @property-read End                      $title
 * @property-read End                      $phoneNumber
 * @property-read End                      $text
 * @property-read End                      $visible
 * @property-read End                      $eMailAddress
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
            $this->createAttribute($this->eMailAddress)->readable()->initializable(),        ];

        if ($this->hasManagementPermission()) {
            $properties[] = $this->createAttribute($this->visible)->readable()->filterable()->initializable();
        }

        return $properties;
    }

    public function isReferencable(): bool
    {
        return true;
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

        // create support contact
        $contact = new SupportContact(
            SupportContact::SUPPORT_CONTACT_TYPE_DEFAULT,
            $properties[$this->title->getAsNamesInDotNotation()],
            $properties[$this->phoneNumber->getAsNamesInDotNotation()],
            $properties[$this->eMailAddress->getAsNamesInDotNotation()],
            $properties[$this->text->getAsNamesInDotNotation()],
            $currentCustomer,
            $properties[$this->visible->getAsNamesInDotNotation()],
        );

        // update customer
        $currentCustomer->getContacts()->add($contact);

        // validate entities
        $this->resourceTypeService->validateObject($contact);
        $this->resourceTypeService->validateObject($currentCustomer);

        // build resource change
        $change = new ResourceChange($contact, $this, $properties);
        $change->addEntityToPersist($contact);

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
        $updater->ifPresent($this->eMailAddress, $contact->setEMailAddress(...));
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
