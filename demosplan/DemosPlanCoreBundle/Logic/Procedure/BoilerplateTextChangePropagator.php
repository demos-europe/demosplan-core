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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Webmozart\Assert\Assert;

/**
 * Saves an edited {@see Boilerplate} whose text changed and propagates the change to every
 * statement/segment recommendation that references it by tag.
 *
 * Those recommendations store only the reference tag, so their stored value does not change:
 * neither the generic change tracking nor the Elasticsearch auto-indexing notices anything.
 * Both are therefore triggered explicitly here, from the one place the change originates:
 * a version entry per affected recommendation, then a reindex of the rendered text.
 *
 * The database part is atomic. Reindexing runs after the commit and only logs on failure,
 * since the saved change must not be lost because of the search index.
 */
class BoilerplateTextChangePropagator
{
    public function __construct(
        private readonly BoilerplateTagSubstitutionService $substitutionService,
        private readonly RecommendationVersionService $recommendationVersionService,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly TransactionService $transactionService,
        private readonly ObjectPersisterInterface $segmentPersister,
        private readonly ObjectPersisterInterface $statementPersister,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $oldText the boilerplate's text before the change; the entity already holds the new one
     */
    public function saveAndPropagate(Boilerplate $boilerplate, string $oldText): Boilerplate
    {
        $boilerplateId = $boilerplate->getId();
        $newText = $boilerplate->getText();

        /** @var list<Statement> $changed */
        $changed = $this->transactionService->executeAndFlushInTransaction(
            function ($entityManager) use ($boilerplate, $boilerplateId, $oldText, $newText): array {
                $entityManager->persist($boilerplate);

                $changed = [];
                foreach ($boilerplate->getUsages() as $usage) {
                    $statementOrSegment = $usage->getStatementOrSegment();
                    Assert::isInstanceOf($statementOrSegment, Statement::class);
                    if ($statementOrSegment->isDeleted()) {
                        continue;
                    }

                    $embedded = $statementOrSegment->getRecommendationEmbedded();
                    $oldRendered = $this->substitutionService->substitute($embedded, [$boilerplateId => $oldText]);
                    $newRendered = $this->substitutionService->substitute($embedded, [$boilerplateId => $newText]);
                    if ($oldRendered === $newRendered) {
                        // legacy usage without a tag in the recommendation: nothing visibly changes
                        continue;
                    }

                    $this->recommendationVersionService->recordVersionForBoilerplateTextChange(
                        $statementOrSegment,
                        $oldRendered,
                        $newRendered
                    );
                    $this->entityContentChangeService->createBoilerplateTextChangeEntry(
                        $statementOrSegment,
                        $oldRendered,
                        $newRendered
                    );
                    $changed[] = $statementOrSegment;
                }

                return $changed;
            }
        );

        $this->substitutionService->forgetBoilerplate($boilerplateId);
        $this->reindex($boilerplateId, $changed);

        return $boilerplate;
    }

    /**
     * @param list<Statement> $statementsOrSegments
     */
    private function reindex(string $boilerplateId, array $statementsOrSegments): void
    {
        $segments = array_values(array_filter($statementsOrSegments, static fn (Statement $s): bool => $s instanceof Segment));
        $statements = array_values(array_filter($statementsOrSegments, static fn (Statement $s): bool => !$s instanceof Segment));

        try {
            if ([] !== $segments) {
                $this->segmentPersister->replaceMany($segments);
            }
            if ([] !== $statements) {
                $this->statementPersister->replaceMany($statements);
            }
        } catch (Throwable $e) {
            $this->logger->error(
                'Boilerplate text was saved, but reindexing the affected recommendations failed',
                ['boilerplateId' => $boilerplateId, 'exception' => $e]
            );
        }
    }
}
