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
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template-extends DplanResourceType<Orga>
 *
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
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

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
        return $this->currentUser->getUser()->hasRole(Role::ORGANISATION_ADMINISTRATION) && $this->isMemberOfPlanningOrga();
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
        $allowedProperties = [];
        $allowedProperties[] = $this->createAttribute($this->id)->readable(true);

        if ($this->currentUser->hasPermission('feature_institution_tag_assign')
            || $this->currentUser->hasPermission('feature_institution_tag_read')
        ) {
            $allowedProperties[] = $this->createAttribute($this->name)->readable(true);;
            $allowedProperties[] = $this->createAttribute($this->createdDate)->readable(true)->sortable();;
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
        $violations = new ConstraintViolationList([]);
        $updater = new PropertiesUpdater($properties);
        $updater->ifPresent($this->assignedTags, function (Collection $newAssignedTags) use ($institution, $violations): void {
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
                $violations->addAll($this->validator->validate($removedTag));
            }

            foreach ($newTags as $newTag) {
                $institution->addAssignedTag($newTag);
                $violations->addAll($this->validator->validate($newTag));
            }

            $violations->addAll($this->validator->validate($institution));
        });

        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return new ResourceChange($institution, $this, $properties);
    }

    private function isMemberOfPlanningOrga(): bool
    {
        $orga = $this->currentUser->getUser()->getOrga();
        $subdomain = $this->globalConfig->getSubdomain();
        $isMunicipality = $orga->hasType(OrgaType::MUNICIPALITY, $subdomain);
        $isPlanningAgency = $orga->hasType(OrgaType::PLANNING_AGENCY, $subdomain);
        $isHearingAuthorityAgency = $orga->hasType(OrgaType::HEARING_AUTHORITY_AGENCY, $subdomain);

        return $isMunicipality || $isPlanningAgency || $isHearingAuthorityAgency;
    }
}
