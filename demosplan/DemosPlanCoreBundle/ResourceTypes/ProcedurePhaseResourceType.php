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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhase;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureYmlPhasesRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PhaseDTO;
use EDT\JsonApi\InputHandling\RepositoryInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ProcedurePhase>
 *
 * @property-read End $name
 * @property-read End $translationKey
 * @property-read End $permissionsSet
 * @property-read End $participationState
 * @property-read End $step
 * @property-read End $startDate
 * @property-read End $endDate
 * @property-read End $designatedPhase
 * @property-read End $designatedSwitchDate
 * @property-read End $designatedEndDate
 * @property-read End $designatedPhaseChangeUser
 * @property-read End $iterator
 * @property-read End $phaseType
 */
final class ProcedurePhaseResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ProcedurePhase';
    }

    public function getEntityClass(): string
    {
        return PhaseDTO::class;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(
                static fn (PhaseDTO $phase) => $phase->getPhaseType() . '_' . $phase->getKey()),
            $this->createAttribute($this->translationKey)->aliasedPath([ProcedureYmlPhasesRepository::PROCEDURE_PHASE_NAME])->readable(),
            $this->createAttribute($this->name)->aliasedPath([ProcedureYmlPhasesRepository::PROCEDURE_PHASE_KEY])->readable(),
            $this->createAttribute($this->permissionsSet)->readable(),
            $this->createAttribute($this->participationState)->readable(),
            $this->createAttribute($this->phaseType)->readable(),


        ];
    }

    protected function getRepository(): RepositoryInterface
    {
        return new ProcedureYmlPhasesRepository($this->globalConfig);
    }
}
