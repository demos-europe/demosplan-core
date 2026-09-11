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
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

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
        return $this->currentUser->hasAllPermissions(
            'area_statement_segmentation',
            'feature_procedure_user_filter_sets'
        );
    }

    /**
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
            $this->conditionFactory->propertyHasValue($user->getId(), Paths::scheduledExport()->user->id),
            $this->conditionFactory->propertyHasValue($procedure->getId(), Paths::scheduledExport()->procedure->id),
            $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue(
                $this->getSegmentListFormatMarker(),
                Paths::scheduledExport()->filterSet->storedQuery
            ),
        ];
    }

    /**
     * Derived from the query class rather than hardcoded, so the two cannot drift apart. It mirrors
     * how the format is written by
     * {@see \demosplan\DemosPlanCoreBundle\Doctrine\Type\StoredQueryType::convertToDatabaseValue()},
     * which encodes `['format' => ..., 'query' => ...]` with `json_encode` - hence no spaces.
     */
    private function getSegmentListFormatMarker(): string
    {
        return sprintf('"format":"%s"', SegmentListQuery::QUERY_FORMAT);
    }
}
