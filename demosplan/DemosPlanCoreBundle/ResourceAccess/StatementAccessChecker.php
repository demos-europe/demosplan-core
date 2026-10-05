<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceAccess;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Webmozart\Assert\Assert;

/**
 * Which statements a user may use through the Statement API, and whether they may update them.
 * Mirrors what the EDT StatementResourceType required.
 */
class StatementAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Mirrors StatementResourceType::isAvailable().
     */
    public function isAvailable(): bool
    {
        return $this->hasAssessmentPermission()
            || $this->currentUser->hasPermission('area_search_submitter_in_procedures');
    }

    /**
     * Mirrors StatementResourceType::isUpdateAllowed().
     */
    public function isUpdateAllowed(): bool
    {
        if (!$this->hasAssessmentPermission()) {
            return false;
        }

        return $this->currentUser->hasPermission('field_statements_custom_fields')
            || $this->currentUser->hasAllPermissions('feature_statement_assignment', 'area_admin_statement_list')
            // allow access for the consultation token admin list
            || $this->currentUser->hasPermission('area_admin_consultations');
    }

    /**
     * Mirrors StatementResourceType::getAccessConditions(): only statements of the current procedure
     * (and the procedures it may access that the user owns), never deleted statements, head
     * statements, placeholders of moved statements, segments or original statements.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        $user = $this->currentUser->getUser();
        Assert::isInstanceOf($user, User::class);
        /** @var list<Procedure> $configuredProcedures */
        $configuredProcedures = $procedure->getSettings()->getAllowedSegmentAccessProcedures()->getValues();

        $allowedProcedureIds = $this->procedureAccessEvaluator->filterNonOwnedProcedureIds($user, ...$configuredProcedures);
        $allowedProcedureIds[] = $procedure->getId();

        return [
            $this->conditionFactory->propertyHasValue(false, ['deleted']),
            $this->conditionFactory->propertyIsNull(['headStatement', 'id']),
            $this->conditionFactory->propertyIsNull(['movedStatement']),
            $this->conditionFactory->propertyHasAnyOfValues($allowedProcedureIds, ['procedure', 'id']),
            $this->conditionFactory->propertyIsNull(['parentStatementOfSegment']),
            $this->conditionFactory->propertyIsNotNull(['original', 'id']),
        ];
    }

    private function hasAssessmentPermission(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'feature_json_api_statement',
            // allow access for the consultation token admin list
            'area_admin_consultations'
        );
    }
}
