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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InvitedPublicAgencyResourceConfigBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\DefaultInclude;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Orga>
 */
class InvitedPublicAgencyResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'InvitedToeb';
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

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending(Paths::orga()->name),
        ];
    }

    /**
     * @return ClauseFunctionInterface[]
     *
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }
        $invitedOrgaIds = $procedure->getOrganisation()->map(
            static fn (OrgaInterface $orga): string => $orga->getId()
        );
        // use least strict rules to even show by now rejected orgas that still had received an ivitation
        $conditions = $this->resourceTypeStore->getOrgaResourceType()->getMandatoryConditions();
        $conditions[] = $this->conditionFactory->propertyHasAnyOfValues($invitedOrgaIds->toArray(), Paths::orga()->id);

        return $conditions;
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        /** @var InvitedPublicAgencyResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(InvitedPublicAgencyResourceConfigBuilder::class);

        // Add identifier property
        $configBuilder->id->setReadableByPath();

        // Base properties that are always readable
        $configBuilder->legalName
            ->setReadableByPath(DefaultField::YES)
            ->setAliasedPath(Paths::orga()->name)
            ->setFilterable();
        $configBuilder->participationFeedbackEmailAddress->setReadableByPath()->setAliasedPath(Paths::orga()->email2);
        $configBuilder->locationContacts
            ->setRelationshipType($this->resourceTypeStore->getInstitutionLocationContactResourceType())
            ->setReadableByPath()
            ->setAliasedPath(Paths::orga()->addresses);

        // Conditional properties based on permissions
        if ($this->currentUser->hasPermission('field_organisation_competence')) {
            $configBuilder->competenceDescription->setAliasedPath(Paths::orga()->competence)
                ->setReadableByPath()
                ->setFilterable();
        }

        if ($this->currentUser->hasPermission('feature_institution_tag_read')) {
            $configBuilder->assignedTags
                ->setRelationshipType($this->resourceTypeStore->getInstitutionTagResourceType())
                ->setReadableByPath(DefaultField::YES, DefaultInclude::YES)
                ->setFilterable();
        }

        // todo transmit count of statements handed in by orga within Procedure
        // todo add bool indicating this orga had an invitation sent via email within thin procedure phase

        return $configBuilder;
    }
}
