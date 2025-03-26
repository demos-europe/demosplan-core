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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ProcedureResourceTypeInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\PhasePermissionsetLoader;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListUserFilter;
use demosplan\DemosPlanCoreBundle\Twig\Extension\ProcedureExtension;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ProcedureInterface>
 *
 * @property-read End                                 $name
 * @property-read End                                 $master
 * @property-read End                                 $deleted
 * @property-read End                                 $agencyMainEmailAddress
 * @property-read OrgaResourceType                    $owningOrganisation
 * @property-read OrgaResourceType                    $invitedOrganisations
 * @property-read OrgaResourceType                    $orga                         Do not expose! Alias usage only.
 * @property-read OrgaResourceType                    $organisation                 Do not expose! Alias usage only.
 * @property-read ProcedureTypeResourceType           $procedureType
 * @property-read ProcedureUiDefinitionResourceType   $procedureUiDefinition
 * @property-read StatementFormDefinitionResourceType $statementFormDefinition
 * @property-read UserResourceType                    $authorizedUsers
 * @property-read OrgaResourceType                    $planningOffices
 * @property-read End                                 $coordinate
 * @property-read ProcedureMapSettingResourceType     $mapSetting
 * @property-read End                                 $externalDesc
 * @property-read End                                 $externalDescription
 * @property-read End                                 $externalName
 * @property-read End                                 $externalStartDate
 * @property-read End                                 $externalEndDate
 * @property-read End                                 $externalPhaseTranslationKey
 * @property-read End                                 $publicParticipationStartDate
 * @property-read End                                 $publicParticipationEndDate
 * @property-read End                                 $internalStartDate
 * @property-read End                                 $startDate
 * @property-read End                                 $endDate
 * @property-read End                                 $internalEndDate
 * @property-read End                                 $internalPhaseTranslationKey
 * @property-read End                                 $daysLeft
 * @property-read End                                 $statementSubmitted
 * @property-read End                                 $owningOrganisationName
 * @property-read End                                 $externalPhasePermissionset
 * @property-read End                                 $internalPhasePermissionset
 * @property-read End                                 $segmentCustomFieldsTemplate
 * @property-read CustomerResourceType                $customer
 * @property-read PlanningDocumentCategoryDetailsResourceType                $availableElements
 * @property-read PlanningDocumentCategoryDetailsResourceType                $elements
 */
final class ProcedureResourceType extends DplanResourceType implements ProcedureResourceTypeInterface
{
    public function __construct(
        private readonly PhasePermissionsetLoader $phasePermissionsetLoader,
        private readonly DraftStatementService $draftStatementService,
        private readonly ProcedureAccessEvaluator $accessEvaluator,
        private readonly ProcedureExtension $procedureExtension,
    ) {
    }

    public function getEntityClass(): string
    {
        return Procedure::class;
    }

    public static function getName(): string
    {
        return 'Procedure';
    }

    public function isAvailable(): bool
    {
        return $this->hasAdminPermissions() || $this->currentUser->hasPermission('area_public_participation');
    }

    protected function getAccessConditions(): array
    {
        $user = $this->currentUser->getUser();
        $userOrganisation = $user->getOrga();
        // users without organisation get no access to any procedure
        if (null === $userOrganisation) {
            return [$this->conditionFactory->false()];
        }

        $procedure = $this->currentProcedureService->getProcedure();
        $userOrganisationId = $userOrganisation->getId();
        $isAllowedAsDataInputOrga = $this->accessEvaluator->isAllowedAsDataInputOrga($user, $procedure);

        // Check if user is data input user and their orga is set as data input orga for current procedure
        $dataInputCondition = $this->conditionFactory->false();
        if ($isAllowedAsDataInputOrga) {
            // as `$isAllowedAsDataInputOrga` is `true`, `$procedure` can't be `null` at this point
            $dataInputCondition = $this->conditionFactory->propertyHasValue($procedure->getId(), $this->id);
        }

        // check for owning organisation
        $owningOrgaCondition = $this->conditionFactory->propertyHasValue($userOrganisationId, $this->orga->id);
        // check for invited organisation
        $invitedOrgaCondition = $this->conditionFactory->propertyHasValue($userOrganisationId, $this->organisation->id);
        // check for allowed planning offices
        $planningOfficesCondition = $this->conditionFactory->propertyHasValue($userOrganisationId, $this->planningOffices->id);

        $conditions = $this->getResourceTypeConditions();

        // users only get access to a procedure if they are either in the organisation owning the procedure
        // or if they are in an organisation that was invited to the procedure (e.g. public interest bodies).
        $conditions[] = $this->conditionFactory->anyConditionApplies(
            $owningOrgaCondition,
            $invitedOrgaCondition,
            $dataInputCondition,
            $planningOfficesCondition
        );

        return $conditions;
    }

    /**
     * Defines the condition that must be met by {@link Procedure} entities to be considered
     * a procedure resource at all, independent of authorizations.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getResourceTypeConditions(): array
    {
        // procedure resources can never be blueprints
        $noBlueprintCondition = $this->conditionFactory->anyConditionApplies(
            /*
             * For some reason the property is explicitly set to be integer ({@link Procedure::master}),
             * until the property is migrated the following condition ensures to handle the int correcly.
             */
            $this->conditionFactory->propertyHasValue(0, $this->master),
            $this->conditionFactory->propertyHasValue(false, $this->master)
        );
        // procedure resources can never have the deleted state
        $undeletedCondition = $this->conditionFactory->propertyHasValue(false, $this->deleted);
        // only procedure templates are tied to a customer
        $customerCondition = $this->conditionFactory->propertyHasValue(
            $this->currentCustomerService->getCurrentCustomer()->getId(),
            Paths::procedure()->customer->id
        );

        return [
            $noBlueprintCondition,
            $undeletedCondition,
            $customerCondition,
        ];
    }

    protected function getProperties(): array
    {
        $external = $this->currentUser->getUser()->isPublicUser();

        $owningOrganisation = $this->createToOneRelationship($this->owningOrganisation)->aliasedPath($this->orga);
        $invitedOrganisations = $this->createToManyRelationship($this->invitedOrganisations)->aliasedPath($this->organisation);
        $properties = [
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->name)->readable(true, fn (Procedure $procedure): ?string => !$external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $procedure->getName()
                : null)->sortable()->filterable(),
            $owningOrganisation,
            $invitedOrganisations,
        ];

        $properties[] = $this->createToManyRelationship($this->availableElements)->readable()->sortable()->filterable()->aliasedPath($this->elements);
        if ($this->hasAdminPermissions()) {
            $owningOrganisation->readable()->sortable()->filterable();
            $invitedOrganisations->readable()->sortable()->filterable();
            $properties[] = $this->createAttribute($this->agencyMainEmailAddress)->readable(true)->sortable()->filterable();
        }

        if ($this->currentUser->hasPermission('area_procedure_type_edit')) {
            $properties[] = $this->createToOneRelationship($this->procedureType)->readable()->sortable()->filterable();
            $properties[] = $this->createToOneRelationship($this->procedureUiDefinition)->readable()->sortable()->filterable();
            $properties[] = $this->createToOneRelationship($this->statementFormDefinition)->readable()->sortable()->filterable();
        }
        if ($this->currentUser->hasAnyPermissions('area_public_participation', 'area_admin_map')) {
            $properties[] = $this->createAttribute($this->coordinate)->readable()->aliasedPath(Paths::procedure()->settings->coordinate);
            $properties[] = $this->createToOneRelationship($this->mapSetting)->aliasedPath(Paths::procedure()->settings)->readable();
        }

        if ($this->currentUser->hasPermission('area_public_participation')) {
            $properties[] = $this->createAttribute($this->externalDescription)->readable()->aliasedPath($this->externalDesc);
            $properties[] = $this->createAttribute($this->statementSubmitted)->readable(false, function (Procedure $procedure): int {
                // guests can not have any draft statements
                if ($this->currentUser->getUser()->isGuestOnly()) {
                    return 0;
                }
                $userFilter = new StatementListUserFilter();
                $userFilter->setSubmitted(true)->setReleased(true);
                $statementResult = $this->draftStatementService->getDraftStatementList(
                    $procedure->getId(),
                    'group',
                    $userFilter,
                    null,
                    null,
                    $this->currentUser->getUser()
                );

                if (!\is_array($statementResult->getResult())) {
                    return 0;
                }

                return count($statementResult->getResult());
            });

            $properties[] = $this->createAttribute($this->externalName)->readable(false, fn (Procedure $procedure): ?string => $external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $procedure->getExternalName()
                : null);
            $properties[] = $this->createAttribute($this->externalStartDate)->readable(false, fn (Procedure $procedure): ?string => $external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->formatDate($procedure->getPublicParticipationStartDate())
                : null);
            $properties[] = $this->createAttribute($this->externalEndDate)->readable(false, fn (Procedure $procedure): ?string => $external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->formatDate($procedure->getPublicParticipationEndDate())
                : null);
            $properties[] = $this->createAttribute($this->externalPhaseTranslationKey)->readable(false, fn (Procedure $procedure): ?string => $external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->globalConfig->getExternalPhaseTranslationKey($procedure->getPublicParticipationPhase())
                : null);
            $properties[] = $this->createAttribute($this->internalStartDate)->readable(false, fn (Procedure $procedure): ?string => !$external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->formatDate($procedure->getStartDate())
                : null);
            $properties[] = $this->createAttribute($this->internalEndDate)->readable(false, fn (Procedure $procedure): ?string => !$external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->formatDate($procedure->getEndDate())
                : null);
            $properties[] = $this->createAttribute($this->internalPhaseTranslationKey)->readable(false, fn (Procedure $procedure): ?string => !$external || $this->accessEvaluator->isOwningProcedure($this->currentUser->getUser(), $procedure)
                ? $this->globalConfig->getInternalPhaseTranslationKey($procedure->getPhase())
                : null);
            $properties[] = $this->createAttribute($this->owningOrganisationName)->readable()->aliasedPath($this->orga->name);

            // T18749
            $properties[] = $this->createAttribute($this->daysLeft)->readable(false, function (Procedure $procedure): string {
                return $this->procedureExtension->getDaysLeftFromProcedureObject($procedure, 'auto'); // type?
            });

            $properties[] = $this->createAttribute($this->internalPhasePermissionset)
                ->readable(false, $this->phasePermissionsetLoader->getInternalPhasePermissionset(...));
            $properties[] = $this->createAttribute($this->externalPhasePermissionset)
                ->readable(false, $this->phasePermissionsetLoader->getExternalPhasePermissionset(...));


        }

        return $properties;
    }

    protected function hasAdminPermissions(): bool
    {
        return $this->currentUser->hasAnyPermissions('area_admin_single_document', 'area_procedure_type_edit', 'feature_json_api_procedure')
            || $this->currentUser->hasAllPermissions('area_admin_procedures', 'area_search_submitter_in_procedures');
    }
}
