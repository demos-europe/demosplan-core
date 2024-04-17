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
use demosplan\DemosPlanCoreBundle\Entity\OriginalStatementAnonymization;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\OriginalStatementAnonymizationResourceConfig;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class OriginalStatementAnonymizationResourceType extends DplanResourceType
{
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(OriginalStatementAnonymizationResourceConfig::class);
        $configBuilder->id
            ->readable();
        $configBuilder->submitterAndAuthorMetaDataAnonymized
            ->readable();

        return $configBuilder;
    }

    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentProcedureService->getProcedure();
        if (null === $currentProcedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $currentProcedure->getId();

        return [
            $this->conditionFactory->propertyHasValue($procedureId, Paths::gdprConsent()->statement->procedure->id),
            $this->conditionFactory->propertyHasValue(false, Paths::gdprConsent()->statement->deleted),
        ];
    }

    public static function getName(): string
    {
        return 'OriginalStatementAnonymization';
    }

    public function getEntityClass(): string
    {
        return OriginalStatementAnonymization::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();
    }
}
