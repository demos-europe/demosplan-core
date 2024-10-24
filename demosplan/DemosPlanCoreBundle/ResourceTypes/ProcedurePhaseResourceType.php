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
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ProcedurePhase>
 *
 * @property-read End $name
 * @property-read End $key
 * @property-read End $permissionsSet
 * @property-read End $step
 * @property-read End $startDate
 * @property-read End $endDate
 * @property-read End $designatedPhase
 * @property-read End $designatedSwitchDate
 * @property-read End $designatedEndDate
 * @property-read End $designatedPhaseChangeUser
 * @property-read End $iterator
 */
final class ProcedurePhaseResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ProcedurePhase';
    }

    public function getEntityClass(): string
    {
        return ProcedurePhase::class;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->false()];
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
        return [];
    }
}
