<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgePendingBoilerplateDeletionsMessageHandler;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Webmozart\Assert\Assert;

/**
 * Materializes a {@see Boilerplate}'s content into every one of its usages, then deletes
 * the row itself. Runs asynchronously off a recurring background job
 * ({@see PurgePendingBoilerplateDeletionsMessageHandler}), not synchronously in the delete
 * request, since rewriting a heavily-used boilerplate's usages could be slow.
 *
 * Atomic per boilerplate: if rewriting any one usage fails, the whole deletion for that
 * boilerplate is aborted and left flagged for retry. A failure also ends the caller's
 * whole purge-batch loop for that tick ({@see ProcedureHandler::purgePendingBoilerplateDeletions()}):
 * a failed flush closes the EntityManager for the rest of the request (Doctrine's own
 * behavior), and leftover, never-flushed entities from this attempt (e.g. a scheduled
 * {@see RecommendationVersion}) would otherwise corrupt a later, unrelated boilerplate in
 * the same batch.
 */
class BoilerplateDeletionService
{
    public function __construct(
        private readonly BoilerplateTagSubstitutionService $substitutionService,
        private readonly BoilerplateRepository $boilerplateRepository,
        private readonly TransactionService $transactionService,
        private readonly EntityContentChangeService $entityContentChangeService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function materializeAndDelete(Boilerplate $boilerplate): bool
    {
        $boilerplateId = $boilerplate->getId();
        $boilerplateTitle = $boilerplate->getTitle();
        $replacementText = $boilerplate->getText();

        // The cast is redundant at runtime (the closure below is already declared `: bool`)
        // — it's here so PhpStorm's native inspection, which doesn't resolve
        // executeAndFlushInTransaction()'s generic template (`callable(EntityManager): T` /
        // `@phpstan-return T`), sees an unambiguous bool instead of flagging the closure
        // argument itself. PHPStan CLI resolves the generic correctly either way.
        return (bool) $this->transactionService->executeAndFlushInTransaction(
            function () use ($boilerplate, $boilerplateId, $boilerplateTitle, $replacementText): bool {
                foreach ($boilerplate->getUsages() as $usage) {
                    $statementOrSegment = $usage->getStatementOrSegment();
                    // getRecommendationEmbedded() is DPLAN-18271-specific, not declared on
                    // the addon's StatementInterface/SegmentInterface contracts. Asserting
                    // against Statement alone is sufficient: Segment extends Statement, so
                    // both a plain Statement and a Segment satisfy this check.
                    Assert::isInstanceOf($statementOrSegment, Statement::class);
                    $materializedText = $this->substitutionService->materializeBoilerplate(
                        $statementOrSegment->getRecommendationEmbedded(),
                        $boilerplateId,
                        $replacementText
                    );
                    $statementOrSegment->setRecommendation($materializedText);
                    $this->entityContentChangeService->createBoilerplateMaterializationChangeEntry(
                        $statementOrSegment,
                        $boilerplateTitle,
                        $replacementText
                    );
                }

                return $this->boilerplateRepository->delete($boilerplateId);
            }
        );
    }
}
