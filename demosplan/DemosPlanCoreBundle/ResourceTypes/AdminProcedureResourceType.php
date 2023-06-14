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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * This class limits the access to {@link Procedure} instances to those, that are allowed
 * to be shown in the procedure administration list for the authenticated user.
 *
 * @template-extends DplanResourceType<Procedure>
 *
 * @property-read StatementResourceType $statements
 * @property-read End                   $name
 * @property-read End                   $createdDate
 * @property-read End                   $creationDate
 * @property-read End                   $deleted
 * @property-read OrgaResourceType      $planningOffices
 * @property-read OrgaResourceType      $orga
 * @property-read End                   $master
 * @property-read UserResourceType      $authorizedUsers
 * @property-read End                   $externalName
 * @property-read End                   $internalStartDate
 * @property-read End                   $startDate
 * @property-read End                   $endDate
 * @property-read End                   $internalEndDate
 * @property-read End                   $originalStatementsCount
 * @property-read End                   $statementsCount
 * @property-read End                   $phase
 * @property-read End                   $phaseName
 * @property-read End                   $internalPhaseIdentifier
 * @property-read End                   $internalPhaseTranslationKey
 * @property-read End                   $publicParticipation
 * @property-read End                   $publicParticipationEndDate
 * @property-read End                   $externalEndDate
 * @property-read End                   $publicParticipationPhase
 * @property-read End                   $publicParticipationStartDate
 * @property-read End                   $externalStartDate
 * @property-read End                   $externalPhaseIdentifier
 * @property-read End                   $externalPhaseTranslationKey
 * @property-read CustomerResourceType  $customer
 */
final class AdminProcedureResourceType extends DplanResourceType
{
    /**
     * @var ProcedureService
     */
    private $procedureService;

    /**
     * @var ProcedureResourceType
     */
    private $procedureResourceType;

    public function __construct(ProcedureResourceType $procedureResourceType, ProcedureService $procedureService)
    {
        $this->procedureService = $procedureService;
        $this->procedureResourceType = $procedureResourceType;
    }

    public static function getName(): string
    {
        return 'AdminProcedure';
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        $id = $this->createAttribute($this->id)->readable(true);
        $name = $this->createAttribute($this->name);
        $creationDate = $this->createAttribute($this->creationDate)->aliasedPath($this->createdDate);

        $properties = [
            $id,
            $name,
            $creationDate,
        ];

        if ($this->currentUser->hasPermission('area_search_submitter_in_procedures')) {
            $id->filterable();
            $name->sortable()->readable();
            $creationDate->sortable();
            $properties[] = $this->createToManyRelationship($this->statements)->filterable();
        }

        if ($this->currentUser->hasPermission('area_admin_procedures')) {
            $name->sortable()->readable();
            $creationDate->sortable()->readable();

            $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
            $externalPhases = $this->globalConfig->getExternalPhasesAssoc();

            $properties = array_merge($properties, [
                $this->createAttribute($this->externalName)->readable(),
                $this->createAttribute($this->internalStartDate)->readable()->aliasedPath($this->startDate),
                $this->createAttribute($this->internalEndDate)->readable()->aliasedPath($this->endDate),
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
                $this->createAttribute($this->internalPhaseIdentifier)->readable()->aliasedPath($this->phase),
                $this->createAttribute($this->internalPhaseTranslationKey)
                    ->readable(false, static function (Procedure $procedure) use ($internalPhases): string {
                        $internalPhaseIdentifier = $procedure->getPhase();

                        return $internalPhases[$internalPhaseIdentifier]['name'] ?? $internalPhaseIdentifier;
                    }),
                $this->createAttribute($this->publicParticipation)->readable(),
                $this->createAttribute($this->externalEndDate)->readable()->aliasedPath($this->publicParticipationEndDate),
                $this->createAttribute($this->externalPhaseIdentifier)->readable()->aliasedPath($this->publicParticipationPhase),
                $this->createAttribute($this->externalPhaseTranslationKey)
                    ->readable(false, static function (Procedure $procedure) use ($externalPhases): string {
                        $externalPhaseIdentifier = $procedure->getPublicParticipationPhase();

                        return $externalPhases[$externalPhaseIdentifier]['name'] ?? $externalPhaseIdentifier;
                    }),
                $this->createAttribute($this->externalStartDate)->readable()->aliasedPath($this->publicParticipationStartDate),
            ]);
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

    public function getAccessCondition(): PathsBasedInterface
    {
        $conditions = $this->procedureService->getAdminProcedureConditions(
            false,
            $this->currentUser->getUser()
        );
        $conditions[] = $this->procedureResourceType->getResourceTypeCondition();

        return $this->conditionFactory->allConditionsApply(...$conditions);
    }
}
