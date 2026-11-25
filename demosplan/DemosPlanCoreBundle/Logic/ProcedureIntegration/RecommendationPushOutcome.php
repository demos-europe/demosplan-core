<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration;

/**
 * What happened to one pushed recommendation.
 *
 * The distinction between skipped and failed matters to the caller: skipped means the source had
 * nothing to say and the target was deliberately left untouched, failed means the entry could not be
 * applied and the planner has to act.
 */
enum RecommendationPushOutcome: string
{
    case UPDATED = 'updated';

    /**
     * The incoming recommendation was empty, so writing it would have erased the existing value
     * rather than doing nothing.
     */
    case SKIPPED_EMPTY = 'skipped_empty';

    case FAILED_UNKNOWN_TARGET = 'failed_unknown_target';

    case FAILED_FOREIGN_PROCEDURE = 'failed_foreign_procedure';

    /**
     * Resolved, in the right procedure, but not a row the assessment table would show: an original,
     * a deleted statement, a move placeholder, or a segment.
     */
    case FAILED_NOT_UPDATABLE = 'failed_not_updatable';

    public function isFailure(): bool
    {
        return match ($this) {
            self::UPDATED, self::SKIPPED_EMPTY => false,
            default                            => true,
        };
    }
}
