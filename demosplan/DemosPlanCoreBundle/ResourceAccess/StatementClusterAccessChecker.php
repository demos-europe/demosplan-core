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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementClusterConditions;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StatementClusterAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly StatementClusterConditions $clusterConditions,
    ) {
    }

    public function isClusterAccessAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_cluster');
    }

    public function checkClusterAccess(): void
    {
        if (!$this->isClusterAccessAllowed()) {
            throw new AccessDeniedHttpException('Access denied: insufficient permissions to access statement groups');
        }
    }

    /**
     * Mirrors ClusterStatementResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        return $this->clusterConditions->forProcedure($procedure->getId());
    }
}
