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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseOrgaResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use Doctrine\Common\Collections\ArrayCollection;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\DefaultInclude;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\CallbackToManyRelationshipSetBehavior;

/**
 * @template-extends DplanResourceType<Orga>
 */
final class InvitableInstitutionResourceType extends DplanResourceType
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

    public function isGetAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read');
    }

    public function isListAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_assign');
    }

    protected function getAccessConditions(): array
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();

        return [
            $this->conditionFactory->propertyHasValue(false, Paths::orga()->deleted),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                Paths::orga()->statusInCustomers->status
            ),
            $this->conditionFactory->propertyHasValue(
                RoleInterface::GPSORG,
                Paths::orga()->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaTypeInterface::PUBLIC_AGENCY,
                Paths::orga()->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $customer->getId(),
                Paths::orga()->statusInCustomers->customer->id
            ),
        ];
    }


    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var BaseOrgaResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(BaseOrgaResourceConfigBuilder::class);

        // Add identifier property
        $configBuilder->id->setReadableByPath();

        if ($this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read')
        ) {
            $configBuilder->name->setReadableByPath(DefaultField::YES)->setFilterable();
            $configBuilder->createdDate->setReadableByPath(DefaultField::YES)->setSortable();
            $configBuilder->assignedTags
                ->setRelationshipType($this->resourceTypeStore->getInstitutionTagResourceType())
                ->setReadableByPath(DefaultField::YES, DefaultInclude::YES)
                ->setFilterable();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_update')) {
            $configBuilder->assignedTags->addUpdateBehavior(
                CallbackToManyRelationshipSetBehavior::createFactory(
                    function (Orga $institution, array $newAssignedTags): array {
                        $newAssignedTags = new ArrayCollection($newAssignedTags);
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

                        return [];
                    },
                    [],
                    OptionalField::YES,
                    []
                )
            );
        }

        return $configBuilder;
    }
}
