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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<ProcedureSettings>
 *
 * @property-read End $coordinate
 */
class ProcedureSettingsResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'ProcedureSettings';
    }

    protected function getProperties(): array
    {
        return [];
    }

    public function getEntityClass(): string
    {
        return ProcedureSettings::class;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [];
    }
}
