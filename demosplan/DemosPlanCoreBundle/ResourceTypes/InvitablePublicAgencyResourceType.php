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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InvitablePublicAgencyResourceConfigBuilder;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\DefaultInclude;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Orga>
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
            $this->conditionFactory->propertyHasValue(false, Paths::orga()->deleted),
            $this->conditionFactory->propertyHasValue(true, Paths::orga()->showlist),
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
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                Paths::orga()->statusInCustomers->status
            ),
        ];
        // avoid already invited organisations
        $invitedOrgaIdsCondition[] = [] === $invitedOrgaIds->toArray()
            ? $this->conditionFactory->true()
            : $this->conditionFactory->propertyHasNotAnyOfValues($invitedOrgaIds->toArray(), Paths::orga()->id);

        return array_merge($conditions, $invitedOrgaIdsCondition);
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending(Paths::orga()->name),
        ];
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        /** @var InvitablePublicAgencyResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(InvitablePublicAgencyResourceConfigBuilder::class);

        // Add identifier property
        $configBuilder->id->setReadableByPath();

        // Base properties that are always readable
        $configBuilder->legalName->setReadableByPath(DefaultField::YES)->setAliasedPath(Paths::orga()->name);
        $configBuilder->participationFeedbackEmailAddress->setReadableByPath()->setAliasedPath(Paths::orga()->email2);
        $configBuilder->locationContacts
            ->setRelationshipType($this->resourceTypeStore->getInstitutionLocationContactResourceType())
            ->setReadableByPath()
            ->setAliasedPath(Paths::orga()->addresses);

        // Conditional properties based on permissions
        if ($this->currentUser->hasPermission('field_organisation_competence')) {
            $configBuilder->competenceDescription->setReadableByCallable(
                static function (Orga $orga): ?string {
                    $competenceDescription = $orga->getCompetence();
                    if ('-' === $competenceDescription || '' === $competenceDescription) {
                        return null;
                    }

                    return $competenceDescription;
                },
                DefaultField::YES
            );
        }

        if ($this->currentUser->hasPermission('field_organisation_email2_cc')) {
            $configBuilder->ccEmailAddresses->setReadableByPath()->setAliasedPath(Paths::orga()->ccEmail2);
        }

        if ($this->currentUser->hasPermission('field_organisation_contact_person')) {
            $configBuilder->contactPerson->setReadableByPath();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $configBuilder->assignedTags
                ->setRelationshipType($this->resourceTypeStore->getInstitutionTagResourceType())
                ->setReadableByPath(DefaultField::YES, DefaultInclude::YES)
                ->setFilterable();
        }

        return $configBuilder;
    }
}
