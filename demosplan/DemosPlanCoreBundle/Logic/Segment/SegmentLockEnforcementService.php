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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Central enforcement point for the segment-lock feature.
 *
 * Mirrors the shape of StatementService::isStatementObjectLockedByAssignment:
 * a two-step "is this user blocked from writing this segment?" check, used by
 * every write surface that could touch a segment on a locked workflow place.
 *
 * The feature itself is gated per project by the `segment_lock_by_workflow_place`
 * config parameter; when false this service returns false for every input and
 * the rest of the enforcement chain becomes a no-op.
 */
class SegmentLockEnforcementService
{
    public const CONFIG_PARAM_FEATURE_ENABLED = 'segment_lock_by_workflow_place';
    public const PERMISSION_ADMINISTRATE = 'feature_administrate_segment_lock';

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly PermissionsInterface $permissions,
    ) {
    }

    /**
     * True when the given segment is locked for the current user — meaning any
     * write attempt (text, tags, assignee, place, etc.) should be rejected.
     *
     * False means the caller may proceed; the feature is either disabled for
     * this project, the segment's place is not locked, or the caller holds the
     * administration permission.
     */
    public function isSegmentLockedForCurrentUser(Segment $segment): bool
    {
        return $this->isPlaceLockedForCurrentUser($segment->getPlace());
    }

    /**
     * Lock predicate for an explicit place — use when the caller needs to
     * check the *original* place of a segment before a pending update (via
     * Doctrine change set) rather than the in-memory post-mutation state.
     */
    public function isPlaceLockedForCurrentUser(?Place $place): bool
    {
        if (!$this->isFeatureEnabled()) {
            return false;
        }

        if (!$place instanceof Place || !$place->isLocked()) {
            return false;
        }

        return !$this->permissions->hasPermission(self::PERMISSION_ADMINISTRATE);
    }

    public function isFeatureEnabled(): bool
    {
        return (bool) $this->parameterBag->get(self::CONFIG_PARAM_FEATURE_ENABLED);
    }
}
