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
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\GdprConsentResourceConfigBuilder;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

final class GdprConsentResourceType extends DplanResourceType
{
    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(GdprConsentResourceConfigBuilder::class);
        $configBuilder->id
            ->readable();
        $configBuilder->consentRevoked
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
        return 'GdprConsent';
    }

    public function getEntityClass(): string
    {
        return GdprConsent::class;
    }

    public function isAvailable(): bool
    {
        return null !== $this->currentProcedureService->getProcedure();
    }
}
