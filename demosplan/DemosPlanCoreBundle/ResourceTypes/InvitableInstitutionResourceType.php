<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;


use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-extends DplanResourceType<Orga>
 * @template-implements UpdatableDqlResourceTypeInterface<Orga>
 *
 * @property-read End                              $name
 * @property-read End                              $createdDate
 * @property-read InstitutionTagResourceType       $assignedTags
 * @property-read End                              $deleted
 * @property-read End                              $showlist
 * @property-read UserResourceType                 $users
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 */
class InvitableInstitutionResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
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
        return true;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();

        return $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue(false, ...$this->deleted),
            $this->conditionFactory->propertyHasValue(true, ...$this->showlist),
            $this->conditionFactory->propertyHasValue(
                Role::GPSORG,
                ...$this->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaType::PUBLIC_AGENCY,
                ...$this->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                ...$this->statusInCustomers->customer->id
            ),
        );
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)->readable(true);
        $name = $this->createAttribute($this->name)->readable(true);
        $createdDate = $this->createAttribute($this->createdDate)->readable(true)->sortable();
        $assignedTags =  $this->createAttribute($this->assignedTags)->readable(true)->filterable();
        return [
            $id,
            $name,
            $createdDate,
            $assignedTags,
        ];
    }

    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->assignedTags
        );
    }

    /**
     * @param Orga $institution
     */
    public function updateObject(object $institution, array $properties): ResourceChange
    {
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->assignedTags, function (Collection $newAssignedTags) use ($institution): void {

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
            }

            foreach ($newTags as $newTag) {
                $institution->addAssignedTag($newTag);
            }
        });

        return new ResourceChange($institution, $this, $properties);
    }
}
