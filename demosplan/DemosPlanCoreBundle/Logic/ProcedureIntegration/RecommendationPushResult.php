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
 * Outcome of one pushed recommendation. The target id and its extern id are only set when the
 * target is a statement of the procedure, so nothing about statements elsewhere leaks to the sender.
 */
final readonly class RecommendationPushResult
{
    public function __construct(
        public RecommendationPushOutcome $outcome,
        public ?string $targetId = null,
        public ?string $externId = null,
    ) {
    }
}
