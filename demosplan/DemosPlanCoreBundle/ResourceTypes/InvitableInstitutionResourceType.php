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
        return $this->conditionFactory->true();
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
            /**
             * @var Collection $currentlyAssignedTags
             */
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
