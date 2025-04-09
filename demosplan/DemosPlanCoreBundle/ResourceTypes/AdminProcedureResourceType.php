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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldService;
use demosplan\DemosPlanCoreBundle\Doctrine\Type\CustomFieldType;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use EDT\PathBuilding\End;

/**
 * This class limits the access to {@link Procedure} instances to those, that are allowed
 * to be shown in the procedure administration list for the authenticated user.
 *
 * @template-extends DplanResourceType<Procedure>
 *
 * @property-read StatementResourceType         $statements
 * @property-read End                           $name
 * @property-read End                           $createdDate
 * @property-read End                           $creationDate
 * @property-read End                           $deleted
 * @property-read OrgaResourceType              $planningOffices
 * @property-read OrgaResourceType              $orga
 * @property-read End                           $master
 * @property-read UserResourceType              $authorizedUsers
 * @property-read End                           $externalName
 * @property-read End                           $internalStartDate
 * @property-read End                           $internalEndDate
 * @property-read End                           $originalStatementsCount
 * @property-read End                           $statementsCount
 * @property-read ProcedurePhaseResourceType    $phase
 * @property-read End                           $internalPhaseIdentifier
 * @property-read End                           $internalPhaseTranslationKey
 * @property-read End                           $publicParticipation
 * @property-read End                           $externalEndDate
 * @property-read ProcedurePhaseResourceType    $publicParticipationPhase
 * @property-read End                           $externalStartDate
 * @property-read End                           $externalPhaseIdentifier
 * @property-read End                           $externalPhaseTranslationKey
 * @property-read CustomFieldResourceType       $segmentCustomFieldsTemplate
 * @property-read CustomerResourceType          $customer
 */
final class AdminProcedureResourceType extends DplanResourceType
{
    public function __construct(private readonly ProcedureResourceType $procedureResourceType, private readonly ProcedureService $procedureService, private readonly CustomFieldList $customFieldList, private readonly CustomFieldType $customFieldType, private readonly CustomFieldService $customFieldService, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public static function getName(): string
    {
        return 'AdminProcedure';
    }

    protected function getProperties(): array
    {
        $id = $this->createIdentifier()->readable();
        $name = $this->createAttribute($this->name);
        $creationDate = $this->createAttribute($this->creationDate)->aliasedPath($this->createdDate);
        $segmentCustomFieldsTemplate = $this->createToManyRelationship($this->segmentCustomFieldsTemplate)
            ->readable(true, function (Procedure $procedure): ?ArrayCollection {
                /** @var CustomFieldConfiguration $customFieldConfiguration */
                $customFieldConfiguration = $this->customFieldConfigurationRepository->getCustomFieldConfigurationBySourceEntityId('PROCEDURE', $procedure->getId(), 'SEGMENT');
                if (null === $customFieldConfiguration) {
                    return null;
                }

                /** @var CustomFieldList $segmentCustomfieldsTemplate */
                $segmentCustomfieldsTemplate = $customFieldConfiguration->getConfiguration();

                return new ArrayCollection($segmentCustomfieldsTemplate->getCustomFields());
            });

        $properties = [
            $id,
            $name,
            $creationDate,
            $segmentCustomFieldsTemplate,
        ];

        if ($this->currentUser->hasPermission('area_search_submitter_in_procedures')) {
            $id->filterable();
            $name->sortable()->readable();
            $creationDate->sortable();
            $properties[] = $this->createToManyRelationship($this->statements)->filterable();
        }

        if ($this->currentUser->hasPermission('area_admin_procedures')) {
            $name->sortable()->readable()->filterable();
            $creationDate->sortable()->readable();

            $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
            $externalPhases = $this->globalConfig->getExternalPhasesAssoc();

            $properties = [
                ...$properties,
                $this->createAttribute($this->externalName)->readable(),
                $this->createAttribute($this->internalStartDate)->readable()->aliasedPath($this->phase->startDate),
                $this->createAttribute($this->internalEndDate)->readable()->aliasedPath($this->phase->endDate),
                $this->createAttribute($this->originalStatementsCount)->readable(false, function (Procedure $procedure): int {
                    // optimize performance? it may be possible to use an actual relationship or
                    // otherwise use an RPC route that calculates the count for all procedures at once
                    $procedureId = $procedure->getId();
                    $counts = $this->procedureService->getOriginalStatementsCounts([$procedureId]);

                    return $counts[$procedureId] ?? 0;
                }),
                $this->createAttribute($this->statementsCount)->readable(false, function (Procedure $procedure): int {
                    // optimize performance? it may be possible to use an actual relationship or
                    // otherwise use an RPC route that calculates the count for all procedures at once
                    $procedureId = $procedure->getId();
                    $counts = $this->procedureService->getStatementsCounts([$procedureId]);

                    return $counts[$procedureId] ?? 0;
                }),
                $this->createAttribute($this->internalPhaseIdentifier)->readable()->aliasedPath($this->phase->key),
                $this->createAttribute($this->internalPhaseTranslationKey)->readable(false, static function (Procedure $procedure) use ($internalPhases): string {
                    $internalPhaseIdentifier = $procedure->getPhase();

                    return $internalPhases[$internalPhaseIdentifier]['name'] ?? $internalPhaseIdentifier;
                }),
                $this->createAttribute($this->publicParticipation)->readable(),
                $this->createAttribute($this->externalEndDate)->readable()->aliasedPath($this->publicParticipationPhase->endDate),
                $this->createAttribute($this->externalPhaseIdentifier)->readable()->aliasedPath($this->publicParticipationPhase->key),
                $this->createAttribute($this->externalPhaseTranslationKey)->readable(false, static function (Procedure $procedure) use ($externalPhases): string {
                    $externalPhaseIdentifier = $procedure->getPublicParticipationPhase();

                    return $externalPhases[$externalPhaseIdentifier]['name'] ?? $externalPhaseIdentifier;
                }),
                $this->createAttribute($this->externalStartDate)->readable()->aliasedPath($this->publicParticipationPhase->startDate)];
        }

        return $properties;
    }

    public function getEntityClass(): string
    {
        return Procedure::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_procedures');
    }

    protected function getAccessConditions(): array
    {
        $adminProcedureConditions = $this->procedureService->getAdminProcedureConditions(
            false,
            $this->currentUser->getUser()
        );

        $resourceTypeConditions = $this->procedureResourceType->getResourceTypeConditions();

        return array_merge($adminProcedureConditions, $resourceTypeConditions);
    }

    public function isUpdateAllowed(): bool
    {
        return true;
    }
}
