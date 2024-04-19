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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSettings;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\ProcedureSettingResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
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

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(ProcedureSettingResourceConfigBuilder::class);
        $configBuilder->id
            ->readable();
        $configBuilder->boundingBox
            ->readable();
        $configBuilder->mapExtent
            ->readable();
        $configBuilder->scales
            ->readable();
        $configBuilder->informationUrl
            ->readable();
        $configBuilder->copyright
            ->readable();

        return $configBuilder;

    }

    public function getEntityClass(): string
    {
        return ProcedureSettings::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::procedureSettings()->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::procedureSettings()->procedure->deleted),
        ];
    }
}
