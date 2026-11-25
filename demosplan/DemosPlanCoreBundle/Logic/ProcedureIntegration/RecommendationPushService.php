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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies a recommendation pushed by another system to one statement of a procedure.
 *
 * The target is resolved by primary key and then validated, never searched, so every mismatch is a
 * reportable failure. An empty recommendation is skipped so it cannot erase a value on this side.
 * Each call flushes on its own, so one entry is one transaction and a later failure does not undo it.
 * Callers must not apply the same target twice in one run: every changed
 * {@see Statement::setRecommendation()} records another recommendation version.
 */
readonly class RecommendationPushService
{
    public function __construct(
        private StatementRepository $statementRepository,
        private HTMLSanitizer $htmlSanitizer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function push(Procedure $procedure, string $targetId, string $recommendation): RecommendationPushResult
    {
        $statement = $this->statementRepository->find($targetId);
        if (!$statement instanceof Statement) {
            return new RecommendationPushResult(RecommendationPushOutcome::FAILED_UNKNOWN_TARGET);
        }

        if ($statement->getProcedure()->getId() !== $procedure->getId()) {
            // Worth a log line: a sender handing in ids of another procedure is either misconfigured
            // or probing.
            $this->logger->warning('Recommendation push rejected: statement outside the procedure', [
                'statement_id' => $targetId,
                'procedure_id' => $procedure->getId(),
            ]);

            return new RecommendationPushResult(RecommendationPushOutcome::FAILED_FOREIGN_PROCEDURE);
        }

        $externId = $statement->getExternId();
        if (!$statement->isAssessmentTableRow()) {
            return new RecommendationPushResult(RecommendationPushOutcome::FAILED_NOT_UPDATABLE, $targetId, $externId);
        }

        $recommendation = $this->purify($recommendation);
        if ('' === $recommendation) {
            return new RecommendationPushResult(RecommendationPushOutcome::SKIPPED_EMPTY, $targetId, $externId);
        }

        $statement->setRecommendation($recommendation);
        $this->entityManager->flush();

        return new RecommendationPushResult(RecommendationPushOutcome::UPDATED, $targetId, $externId);
    }

    /**
     * Filtered to the same tag allowlist as editor input. Returns '' when nothing of substance
     * survives, which the caller treats as "skip" rather than "write empty".
     */
    private function purify(string $recommendation): string
    {
        $purified = $this->htmlSanitizer->wysiwygFilter($recommendation, [], true);

        // An image-only recommendation carries no text but is real content, so it must not be
        // mistaken for an empty one and skipped.
        $hasText = '' !== trim(strip_tags($purified));

        return $hasText || str_contains($purified, '<img') ? $purified : '';
    }
}
