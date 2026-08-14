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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies recommendations pushed by another instance to statements of one procedure.
 *
 * Each entry is resolved by primary key and then *validated*, never searched: the pinned procedure
 * comes from the token, so there is no ambiguity to resolve and every mismatch is a reportable
 * failure rather than a near miss.
 *
 * Deliberate properties:
 * - The batch commits what succeeded. A failure does not roll back earlier entries, because a partial
 *   push the planner can see beats an all-or-nothing one they cannot diagnose.
 * - An empty recommendation is skipped rather than written, so a source with nothing to say cannot
 *   erase a value on this side.
 * - {@see Statement::setRecommendation()} is called at most once per statement per request, since each
 *   changed call records another recommendation version.
 */
class RecommendationPushService
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly HTMLSanitizer $htmlSanitizer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, array{sourceStatementId: string, recommendation: string, externId?: string|null}> $entries
     *
     * @return list<RecommendationPushResult>
     */
    public function push(Procedure $procedure, array $entries): array
    {
        $results = [];
        $changed = false;

        // Later entries for the same statement would each record their own recommendation version,
        // so only the first one for a given id is applied.
        $seen = [];

        foreach ($entries as $entry) {
            $sourceStatementId = $entry['sourceStatementId'];
            $externId = $entry['externId'] ?? null;

            if (isset($seen[$sourceStatementId])) {
                $results[] = RecommendationPushResult::skippedDuplicate($sourceStatementId, $externId);
                continue;
            }
            $seen[$sourceStatementId] = true;

            $statement = $this->statementRepository->find($sourceStatementId);
            if (!$statement instanceof Statement) {
                $results[] = RecommendationPushResult::failed(
                    $sourceStatementId,
                    RecommendationPushOutcome::FAILED_UNKNOWN_STATEMENT,
                    $externId
                );
                continue;
            }

            $externId ??= $statement->getExternId();

            if ($statement->getProcedure()->getId() !== $procedure->getId()) {
                // Worth a log line: a token pinned to one procedure being handed ids from another is
                // either a misconfigured pairing or an attempt to reach past the pin.
                $this->logger->warning('Recommendation push rejected: statement outside the pinned procedure', [
                    'statement_id'        => $sourceStatementId,
                    'pinned_procedure_id' => $procedure->getId(),
                ]);
                $results[] = RecommendationPushResult::failed(
                    $sourceStatementId,
                    RecommendationPushOutcome::FAILED_FOREIGN_PROCEDURE,
                    $externId
                );
                continue;
            }

            if (!$this->isAssessable($statement)) {
                $results[] = RecommendationPushResult::failed(
                    $sourceStatementId,
                    RecommendationPushOutcome::FAILED_NOT_ASSESSABLE,
                    $externId
                );
                continue;
            }

            $recommendation = $this->purify($entry['recommendation']);
            if ('' === $recommendation) {
                $results[] = RecommendationPushResult::skippedEmpty($sourceStatementId, $externId);
                continue;
            }

            $statement->setRecommendation($recommendation);
            $changed = true;
            $results[] = RecommendationPushResult::pushed($sourceStatementId, $externId);
        }

        if ($changed) {
            $this->entityManager->flush();
        }

        return $results;
    }

    /**
     * Whether the statement is a row the assessment table would show, which is the only kind that may
     * be written: originals are read-only, and deleted statements, move placeholders and segments are
     * not editable rows at all.
     */
    private function isAssessable(Statement $statement): bool
    {
        return null !== $statement->getOriginal()
            && !$statement->isDeleted()
            && null === $statement->getMovedStatement()
            && !$statement instanceof Segment;
    }

    /**
     * The payload is rich text authored on another instance, so it is filtered to the same tag
     * allowlist as editor input and then run through HTMLPurifier. Returns '' when nothing of
     * substance survives, which the caller treats as "skip" rather than "write empty".
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
