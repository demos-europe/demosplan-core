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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use Webmozart\Assert\Assert;

/**
 * Materializes a {@see Boilerplate}'s content into every one of its usages, then deletes
 * the boilerplate row itself (DPLAN-18271, "Boilerplate deletion" — option (b)).
 *
 * Runs asynchronously off a recurring background job
 * ({@see \demosplan\DemosPlanCoreBundle\MessageHandler\PurgePendingBoilerplateDeletionsMessageHandler}),
 * not synchronously in the delete request: rewriting every usage of a heavily-used
 * boilerplate could be slow, and the request must not risk a timeout. The request only
 * flags the boilerplate ({@see Boilerplate::setPendingDeletion()}); this service does the
 * actual work once picked up.
 *
 * Each recommendation is rewritten via {@see \demosplan\DemosPlanCoreBundle\Entity\Statement\Statement::setRecommendation()},
 * the normal funnel — not a bypass — so version recording, {@see \demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateUsageReconciliationService}
 * and ES reindexing all fire exactly as they would for a manual edit. Reconciliation
 * naturally drops the usage relation because the tag is gone; no separate cleanup needed.
 *
 * The whole sequence is atomic: if rewriting any one usage fails, the entire deletion is
 * aborted and the boilerplate row is left in place (still flagged, so it retries on the
 * next tick) — never left with some usages materialized and others still pointing at a
 * boilerplate that no longer exists.
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

    public function materializeAndDelete(Boilerplate $boilerplate): bool
    {
        $boilerplateId = $boilerplate->getId();
        $boilerplateTitle = $boilerplate->getTitle();
        $replacementText = $boilerplate->getText();

        return $this->transactionService->executeAndFlushInTransaction(
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
