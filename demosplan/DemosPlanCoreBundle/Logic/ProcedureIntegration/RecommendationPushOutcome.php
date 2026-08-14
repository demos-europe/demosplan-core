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
 * What happened to one entry of a recommendation push.
 *
 * The distinction between skipped and failed matters to the caller: skipped means the source had
 * nothing to say and the target was deliberately left untouched, failed means the entry could not be
 * applied and the planner has to act.
 */
enum RecommendationPushOutcome: string
{
    case PUSHED = 'pushed';

    /**
     * The incoming recommendation was empty, so writing it would have erased the existing value
     * rather than doing nothing.
     */
    case SKIPPED_EMPTY = 'skipped_empty';

    /**
     * The same statement appeared earlier in the batch. Only the first entry is applied, because each
     * changed `setRecommendation()` call records another recommendation version.
     */
    case SKIPPED_DUPLICATE = 'skipped_duplicate';

    case FAILED_UNKNOWN_STATEMENT = 'failed_unknown_statement';

    case FAILED_FOREIGN_PROCEDURE = 'failed_foreign_procedure';

    /**
     * Resolved, in the right procedure, but not a row the assessment table would show: an original,
     * a deleted statement, a move placeholder, or a segment.
     */
    case FAILED_NOT_ASSESSABLE = 'failed_not_assessable';

    public function isFailure(): bool
    {
        return match ($this) {
            self::PUSHED, self::SKIPPED_EMPTY, self::SKIPPED_DUPLICATE => false,
            default                                                    => true,
        };
    }
}
