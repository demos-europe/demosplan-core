<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

class AccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Mirrors StatementSegmentResourceType::isAvailable().
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_statement_segment',
            // can be included via statements in a view reachable with the following permissions
            'area_admin_statement_list', 'feature_statements_import_excel'
        );
    }

    /**
     * Mirrors StatementSegmentResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        $procedureId = $procedure->getId();
        $currentUser = $this->currentUser->getUser();
        $allowedProcedures = $procedure
            ->getSettings()
            ->getAllowedSegmentAccessProcedures()
            ->getValues();
        $procedureIds = $this->procedureAccessEvaluator
            ->filterNonOwnedProcedureIds($currentUser, ...$allowedProcedures);
        $procedureIds[] = $procedureId;

        return [] === $procedureIds
            ? [$this->conditionFactory->false()]
            : [$this->conditionFactory->propertyHasAnyOfValues($procedureIds, ['parentStatementOfSegment', 'procedure', 'id'])];
    }
}
