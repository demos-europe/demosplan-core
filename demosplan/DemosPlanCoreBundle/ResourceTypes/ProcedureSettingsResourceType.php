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
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\ScaleDTO;
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
            ->updatable()
            ->readable();
        $configBuilder->mapExtent
            ->updatable()
            ->readable();
        $configBuilder->scales
            ->updatable()
            ->readable();
        $configBuilder->informationUrl
            ->updatable()
            ->readable();
        $configBuilder->copyright
            ->updatable()
            ->readable();
        $configBuilder->publicAvailableScales
            ->setRelationshipType($this->resourceTypeStore->getScaleResourceType())
            ->readable(true, $this->getScales(...));

        return $configBuilder;
    }

    protected function getScales(): array
    {
        $scales = str_replace(['[', ']'], '', (string) $this->globalConfig->getMapPublicAvailableScales());
        $rawAvailableScales = explode(',', $scales);
        $availableScales = [];
        foreach ($rawAvailableScales as $scale) {
            $scaleDto = new ScaleDTO();
            $scaleDto->setScale($scale);
            $scaleDto->lock();
            $availableScales[] = $scaleDto;
        }

        return $availableScales;
    }

    public function getEntityClass(): string
    {
        return ProcedureSettings::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_admin_map'); // @todo update permission
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
