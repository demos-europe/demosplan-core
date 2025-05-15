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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InvitablePublicAgencyResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Orga>
 *
 * @property-read End                              $legalName
 * @property-read End                              $name
 * @property-read End                              $competenceDescription
 * @property-read End                              $deleted
 * @property-read End                              $showlist
 * @property-read UserResourceType                 $users
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 * @property-read End                              $participationFeedbackEmailAddress
 * @property-read End                              $ccEmailAddresses
 * @property-read InstitutionLocationContactResourceType $locationContacts
 * @property-read InstitutionTagResourceType $assignedTags
 * @property-read End $contactPerson
 */
class InvitablePublicAgencyResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'InvitableToeb';
    }

    public function getEntityClass(): string
    {
        return Orga::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_main_procedures',
            'area_admin_invitable_institution'
        );
    }

    /**
     * @throws PathException
     * @throws CustomerNotFoundException
     */
    protected function getAccessConditions(): array
    {
        $customer = $this->currentCustomerService->getCurrentCustomer();
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }
        $invitedOrgaIds = $procedure->getOrganisation()->map(
            static fn (Orga $orga): string => $orga->getId()
        );

        $conditions = [
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            $this->conditionFactory->propertyHasValue(true, $this->showlist),
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
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomer::STATUS_ACCEPTED,
                $this->statusInCustomers->status
            ),
        ];
        // avoid already invited organisations
        $invitedOrgaIdsCondition[] = [] === $invitedOrgaIds->toArray()
            ? $this->conditionFactory->true()
            : $this->conditionFactory->propertyHasNotAnyOfValues($invitedOrgaIds->toArray(), $this->id);

        return array_merge($conditions, $invitedOrgaIdsCondition);
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->name),
        ];
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        /** @var InvitablePublicAgencyResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(InvitablePublicAgencyResourceConfigBuilder::class);

        $configBuilder->id->readable();

        // Base properties that are always readable
        $configBuilder->legalName->readable(true)->aliasedPath($this->name);
        $configBuilder->participationFeedbackEmailAddress->readable()->aliasedPath(Paths::orga()->email2);
        $configBuilder->locationContacts
            ->setRelationshipType($this->resourceTypeStore->getInstitutionLocationContactResourceType())
            ->readable()
            ->aliasedPath(Paths::orga()->addresses);

        // Conditional properties based on permissions
        if ($this->currentUser->hasPermission('field_organisation_competence')) {
            $configBuilder->competenceDescription->readable(
                true,
                static function (Orga $orga): ?string {
                    $competenceDescription = $orga->getCompetence();
                    if ('-' === $competenceDescription || '' === $competenceDescription) {
                        return null;
                    }

                    return $competenceDescription;
                }
            );
        }

        if ($this->currentUser->hasPermission('field_organisation_email2_cc')) {
            $configBuilder->ccEmailAddresses->readable()->aliasedPath(Paths::orga()->ccEmail2);
        }

        if ($this->currentUser->hasPermission('field_organisation_contact_person')) {
            $configBuilder->contactPerson->readable();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $configBuilder->assignedTags
                ->setRelationshipType($this->resourceTypeStore->getInstitutionTagResourceType())
                ->readable(true, null, true)
                ->filterable();
        }

        return $configBuilder;
    }
}
