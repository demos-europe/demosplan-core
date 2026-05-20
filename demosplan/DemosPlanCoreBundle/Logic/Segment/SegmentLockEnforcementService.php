<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;

/**
 * Central enforcement point for the segment-lock feature.
 *
 * Mirrors the shape of
 * {{ @see StatementService::isStatementObjectLockedByAssignment }}: a
 * two-step "is this user blocked from writing this segment?" check, used by
 * every write surface that could touch a segment on a locked workflow place.
 *
 * The feature itself is gated per project by the
 * `feature_segment_lock_by_workflow_place` permission; when the current user
 * does not hold it this service returns false for every input and the rest of
 * the enforcement chain becomes a no-op. Granting the permission to every role
 * that has `area_statement_segmentation` is the project-level "on" switch.
 */
class SegmentLockEnforcementService
{
    public const PERMISSION_FEATURE_ENABLED = 'feature_segment_lock_by_workflow_place';
    public const PERMISSION_ADMINISTRATE = 'feature_administrate_segment_lock';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
    ) {
    }

    /**
     * Lock predicate for an explicit place. The two production callers feed
     * in either:
     *  - the *original* place of a segment before a pending update (via
     *    UnitOfWork original entity data) — see
     *    {{ @see SegmentLockEnforcementSubscriber::resolveOriginalPlace }};
     *  - or, indirectly through the bulk-editor's
     *    {{ @see SegmentBulkEditorService::isEnforcementApplicable }}
     *    short-circuit, no place at all (the DB filter does the place lookup).
     *
     * Returns false when the feature is disabled for this project, the
     * place is null or unlocked, or the caller holds the administration
     * permission.
     */
    public function isPlaceLockedForCurrentUser(?Place $place): bool
    {
        if (!$this->isEnforcementApplicable()) {
            return false;
        }

        return $place instanceof Place && $place->isLocked();
    }

    /**
     * True when segment-lock enforcement applies to the current request at all.
     *
     * Composes the two batch-invariant checks (feature flag + administrate
     * permission) so batch callers can short-circuit once instead of asking
     * per segment. When this returns false the rest of the enforcement chain
     * is a no-op for every place in the request.
     */
    public function isEnforcementApplicable(): bool
    {
        return $this->isFeatureEnabled()
            && !$this->currentUser->hasPermission(self::PERMISSION_ADMINISTRATE);
    }

    public function isFeatureEnabled(): bool
    {
        return $this->currentUser->hasPermission(self::PERMISSION_FEATURE_ENABLED);
    }
}
