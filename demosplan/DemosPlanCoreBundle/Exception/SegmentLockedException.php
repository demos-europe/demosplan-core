<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

/**
 * Thrown when a user without the segment-lock administration permission
 * attempts to write a segment whose workflow place has `locked = true`.
 *
 * Mirrors the shape of LockedByAssignmentException for the workflow-place
 * based lock feature. Callers are responsible for composing the exception
 * message and for adding a matching entry to the MessageBag (e.g.
 * `error.segment.locked.by.place` for a single-segment reject or
 * `error.segment.bulk.contains.locked` for a batch reject) so the user
 * sees a clear explanation in addition to the HTTP 403 that Symfony
 * produces for AccessDeniedException subclasses.
 */
class SegmentLockedException extends AccessDeniedException
{
}
