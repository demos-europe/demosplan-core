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
 * Outcome of one pushed entry, keyed by the id the caller sent so it can match results to its own
 * records without relying on ordering.
 *
 * {@see self::$externId} is carried through purely so a report or UI can name the statement the way a
 * human does; nothing resolves by it.
 */
final readonly class RecommendationPushResult
{
    public function __construct(
        public string $sourceStatementId,
        public RecommendationPushOutcome $outcome,
        public ?string $externId = null,
    ) {
    }

    public static function pushed(string $sourceStatementId, ?string $externId): self
    {
        return new self($sourceStatementId, RecommendationPushOutcome::PUSHED, $externId);
    }

    public static function skippedEmpty(string $sourceStatementId, ?string $externId): self
    {
        return new self($sourceStatementId, RecommendationPushOutcome::SKIPPED_EMPTY, $externId);
    }

    public static function skippedDuplicate(string $sourceStatementId, ?string $externId): self
    {
        return new self($sourceStatementId, RecommendationPushOutcome::SKIPPED_DUPLICATE, $externId);
    }

    public static function failed(
        string $sourceStatementId,
        RecommendationPushOutcome $outcome,
        ?string $externId = null,
    ): self {
        return new self($sourceStatementId, $outcome, $externId);
    }
}
