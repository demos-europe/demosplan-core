<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Access rules for scheduled Synopse exports, shared by the provider and the processor so that reads
 * and writes cannot drift apart.
 */
class ScheduledExportAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_admin_scheduled_xlsx_export');
    }

    /**
     * Restricts to the current user's own schedules within the current procedure.
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        $user = $this->currentUser->getUser();
        if (!$user instanceof User) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($user->getId(), Paths::exportSchedule()->userId),
            $this->conditionFactory->propertyHasValue($procedure->getId(), Paths::exportSchedule()->procedureId),
        ];
    }
}
