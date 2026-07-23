<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Place;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

class AccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Mirrors PlaceResourceType::isAvailable().
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_statement_segmentation');
    }

    /**
     * Mirrors PlaceResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        // for now all places can be read by anyone if they are available
        return [
            $this->conditionFactory->propertyHasValue($procedure->getId(), ['procedure', 'id']),
        ];
    }
}
