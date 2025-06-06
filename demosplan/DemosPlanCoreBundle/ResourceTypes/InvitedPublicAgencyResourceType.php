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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
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
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly ProcedureService $procedureService,
        private readonly StatementService $statementService,
    ) {
    }

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

        $configBuilder->hasReceivedInvitationMailInCurrentProcedurePhase
            ->setReadableByCallable(
                fn (OrgaInterface $orga) => $this->hasReceivedInvitationMailInCurrentPhase($orga->getId()),
                DefaultField::YES
            );
        // todo why does the es implementation differ in their counts to the musch easier doctrine version
        $configBuilder->originalStatementsCountInProcedure
            ->setReadableByCallable(
                fn (OrgaInterface $orga) => $this->getOriginalStatementsCountForOrgaES($orga),
                DefaultField::YES
            );

        return $configBuilder;
    }

    /**
     * Repository approach: Uses StatementMeta.submitOrgaId to count statements originally submitted by the organization.
     * This represents the immutable original submitting organization, regardless of any subsequent reassignments.
     */
    private function getOriginalStatementsCountForOrga(string $orgaId): int
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return 0;
        }

        $statements = $this->statementRepository->getStatementsOfProcedureAndOrganisation(
            $procedure->getId(),
            $orgaId
        );

        return count($statements);
    }

    private function hasReceivedInvitationMailInCurrentPhase(string $orgaId): bool
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return false;
        }

        $invitationEmailList = $this->procedureService->getInstitutionMailList(
            $procedure->getId(),
            $procedure->getPhase()
        );

        if (!is_array($invitationEmailList['result']) || 0 === count($invitationEmailList['result'])) {
            return false;
        }

        foreach ($invitationEmailList['result'] as $invitedOrga) {
            if (array_key_exists('organisation', $invitedOrga)
                && $invitedOrga['organisation'] instanceof Orga
                && $invitedOrga['organisation']->getId() === $orgaId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * ES approach: Uses Statement.oId which represents the current organization association of the statement.
     * This may differ from the original submitting organization if statements have been reassigned/transferred.
     * Note: For counting original submissions, this may give different results than the repository approach.
     */
    private function getOriginalStatementsCountForOrgaES(OrgaInterface $orga): int
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return 0;
        }

        $filters = [
            'original' => 'IS NULL',
            'deleted' => false,
        ];
        $statements = $this->statementService->getStatementsByProcedureId(
            $procedure->getId(),
            $filters,
            null,
            null,
            1_000_000
        );

        $count = 0;
        foreach ($statements->getResult() as $statement) {
            if ($statement['oId'] === $orga->getId()) {
                ++$count;
            }
        }

        return $count;
    }
}
