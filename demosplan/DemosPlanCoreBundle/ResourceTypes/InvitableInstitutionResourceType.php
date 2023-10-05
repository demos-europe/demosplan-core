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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Orga>
 *
 * @template-implements UpdatableDqlResourceTypeInterface<Orga>
 *
 * @property-read End                              $name
 * @property-read End                              $createdDate
 * @property-read InstitutionTagResourceType       $assignedTags
 * @property-read End                              $deleted
 * @property-read UserResourceType                 $users
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 */
final class InvitableInstitutionResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public static function getName(): string
    {
        return 'InvitableInstitution';
    }

    public function getEntityClass(): string
    {
        return Orga::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read');
    }

    protected function getAccessConditions(): array
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomer::STATUS_ACCEPTED,
                $this->statusInCustomers->status
            ),
            $this->conditionFactory->propertyHasValue(
                Role::GPSORG,
                $this->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaType::PUBLIC_AGENCY,
                $this->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                $this->statusInCustomers->customer->id
            ),
        ];
    }

    protected function getProperties(): array
    {
        $allowedProperties = [];
        $allowedProperties[] = $this->createAttribute($this->id)->readable(true);

        if ($this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read')
        ) {
            $allowedProperties[] = $this->createAttribute($this->name)->readable(true);
            $allowedProperties[] = $this->createAttribute($this->createdDate)->readable(true)->sortable();
            $allowedProperties[] = $this->createToManyRelationship($this->assignedTags)->readable(true)->filterable();
        }

        return $allowedProperties;
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        if ($this->currentUser->hasPermission('feature_institution_tag_assign')) {
            return $this->toProperties(
                $this->assignedTags
            );
        }

        return [];
    }

    /**
     * @param Orga $institution
     */
    public function updateObject(object $institution, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent(
            $this->assignedTags,
            function (Collection $newAssignedTags) use ($institution): void {
                $currentlyAssignedTags = $institution->getAssignedTags();

                // removed tags
                $removedTags = $currentlyAssignedTags->filter(
                    static fn (InstitutionTag $currentTag): bool => !$newAssignedTags->contains($currentTag)
                );

                // new tags
                $newTags = $newAssignedTags->filter(
                    static fn (InstitutionTag $newTag): bool => !$currentlyAssignedTags->contains($newTag)
                );

                foreach ($removedTags as $removedTag) {
                    $institution->removeAssignedTag($removedTag);
                    $this->resourceTypeService->validateObject($removedTag);
                }

                foreach ($newTags as $newTag) {
                    $institution->addAssignedTag($newTag);
                    $this->resourceTypeService->validateObject($newTag);
                }

                $this->resourceTypeService->validateObject($institution);
            }
        );

        return new ResourceChange($institution, $this, $properties);
    }
}
