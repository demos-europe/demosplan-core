<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Bookmark;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\StoredQuery\SegmentListQuery;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Access rules for the segment list's saved views, shared by the provider and the processor so that
 * reads and writes cannot drift apart.
 */
class BookmarkAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * `feature_procedure_user_filter_sets` is reused rather than replaced by a segment specific
     * permission: in the projects that have segmentation it is granted under the same
     * `ownsProcedure()` condition, so everyone who reaches the segment list already holds it. Its name
     * predates both this use and the Bookmark entity.
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_statement_segmentation',
            'feature_procedure_user_filter_sets'
        );
    }

    /**
     * Restricts to the current user's bookmarks in the current procedure, and to those pointing at a
     * segment list query.
     *
     * The last condition matters because the table is shared with the assessment table, whose saved
     * filters are the same entity. A row carries no marker of its kind - the kind lives in the JSON of
     * the referenced query - so the format is matched inside that text. Emitted SQL is
     * `LOWER(stored_query) LIKE '%"format":"segment_list"%'`, which runs on MySQL and on the SQLite
     * connection the tests use alike.
     *
     * Being a real condition rather than a filter applied after fetching, it also covers reads and
     * writes by id: an assessment table bookmark is simply not findable here.
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
        if (!$user instanceof User) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($user->getId(), Paths::bookmark()->user->id),
            $this->conditionFactory->propertyHasValue($procedure->getId(), Paths::bookmark()->procedure->id),
            $this->conditionFactory->propertyHasStringContainingCaseInsensitiveValue(
                $this->getSegmentListFormatMarker(),
                Paths::bookmark()->filterSet->storedQuery
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
        return sprintf('"format":"%s"', SegmentListQuery::QUIERY_FORMAT);
    }
}
