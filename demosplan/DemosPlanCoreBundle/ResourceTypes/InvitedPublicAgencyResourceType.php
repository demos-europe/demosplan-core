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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\InvitedPublicAgencyResourceConfigBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\DefaultInclude;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Querying\Contracts\PathException;
use Exception;
use Webmozart\Assert\Assert;

/**
 * @template-extends DplanResourceType<Orga>
 */
class InvitedPublicAgencyResourceType extends DplanResourceType
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly ProcedureService $procedureService,
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
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }
        $invitedOrgaIds = $procedure->getOrganisation()->map(
            static fn (OrgaInterface $orga): string => $orga->getId()
        );
        // use least strict rules to even show by now rejected orgas that still had received an invitation
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
        $configBuilder->paperCopy
            ->setReadableByPath()
            ->setFilterable();
        $configBuilder->paperCopySpec
            ->setReadableByPath()
            ->setFilterable();

        // Virtual properties that are always readable
        $configBuilder->hasReceivedInvitationMailInCurrentProcedurePhase
            ->setReadableByCallable(
                fn (OrgaInterface $orga) => $this->hasReceivedInvitationMailInCurrentPhase($orga->getId()),
                DefaultField::YES
            );
        $configBuilder->originalStatementsCountInProcedure
            ->setReadableByCallable(
                fn (OrgaInterface $orga) => $this->getOriginalStatementsCountForOrga($orga->getId()),
                DefaultField::YES
            );

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

        return $configBuilder;
    }

    /**
     * Uses Statement.organisation (_o_id) to count statements submitted via draft-to-statement workflow.
     * This counts statements where the organisation is set directly on the statement, representing
     * statements submitted by the organisation through the draft-to-statement process.
     */
    private function getOriginalStatementsCountForOrga(string $orgaId): int
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return 0;
        }

        try {
            return $this->statementRepository->countDraftToStatementSubmissionsByOrganisation(
                $procedure->getId(),
                $orgaId
            );
        } catch (Exception $e) {
            $this->logger->error(
                'Failed to retrieve original statements count for institution',
                [
                    'orgaId'      => $orgaId,
                    'procedureId' => $procedure->getId(),
                    'exception'   => $e,
                ]
            );
            $this->messageBag->add('error', 'error.statements.of.institution.count.retrieval.failed');

            return 0;
        }
    }

    private function hasReceivedInvitationMailInCurrentPhase(string $orgaId): bool
    {
        try {
            $procedure = $this->currentProcedureService->getProcedure();
            Assert::notNull($procedure);

            $invitationEmailList = $this->procedureService->getInstitutionMailList(
                $procedure->getId(),
                $procedure->getPhase()
            );

            $hasValidResultFormat = is_array($invitationEmailList['result']);
            Assert::true($hasValidResultFormat);
        } catch (Exception $e) {
            $this->logger->error(
                'Failed to retrieve institution invitation mail list',
                [
                    'orgaId'      => $orgaId,
                    'procedureId' => $procedure->getId(),
                    'phase'       => $procedure->getPhase(),
                    'exception'   => $e,
                ]
            );
            $this->messageBag->add('error', 'error.institution.invitation.mail.list.retrieval.failed');

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
}
