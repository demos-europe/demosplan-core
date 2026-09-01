<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateUsage;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateUsageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps {@see BoilerplateUsage} rows in sync with the <dp-boilerplate
 * boilerplate-id="…"> tags actually present in a recommendation (DPLAN-18271).
 *
 * Called from {@see Statement::setRecommendation()} on every save, for both Segment
 * recommendations and plain top-level Statement recommendations — {@see BoilerplateUsage}
 * accepts either (STI, same pattern as {@see RecommendationVersion}).
 *
 * Tag presence is the only signal: a tag in the new text with no existing usage row gets
 * one created; an existing usage row whose tag is no longer present gets removed. There
 * is no content comparison anywhere (see the DPLAN-18271 plan, "Decided Design", hard
 * invariant 3) — the atomic editor node makes editing a linked boilerplate's content
 * impossible in the first place, so drift detection has nothing to detect.
 *
 * Does not call flush() — the caller's transaction handles flushing, same convention as
 * {@see RecommendationVersionService::recordVersion()}.
 */
class BoilerplateUsageReconciliationService
{
    public function __construct(
        private readonly BoilerplateTagSubstitutionService $boilerplateTagSubstitutionService,
        private readonly BoilerplateRepository $boilerplateRepository,
        private readonly BoilerplateUsageRepository $boilerplateUsageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function reconcile(Statement $statement, string $newEmbeddedText): void
    {
        $currentBoilerplateIds = $this->boilerplateTagSubstitutionService->extractBoilerplateIds($newEmbeddedText);
        $existingUsagesByBoilerplateId = $this->boilerplateUsageRepository->findUsagesForStatementOrSegment($statement);

        foreach ($currentBoilerplateIds as $boilerplateId) {
            if (!isset($existingUsagesByBoilerplateId[$boilerplateId])) {
                $this->createUsageIfValid($statement, $boilerplateId);
            }
        }

        foreach ($existingUsagesByBoilerplateId as $boilerplateId => $usage) {
            if (!in_array($boilerplateId, $currentBoilerplateIds, true)) {
                $this->entityManager->remove($usage);
            }
        }
    }

    private function createUsageIfValid(Statement $statement, string $boilerplateId): void
    {
        $boilerplate = $this->boilerplateRepository->find($boilerplateId);

        if (null === $boilerplate) {
            // Residual concurrent-edit race (DPLAN-18271 plan, Trap 8): the boilerplate
            // was deleted after this tag was inserted but before this save reached the
            // server. Nothing to link to; getRecommendation() substitutes an empty
            // string for this tag. Deletion itself materializes content into every
            // existing usage first (see "Boilerplate deletion" in the plan), so this
            // should not occur in normal operation.
            return;
        }

        if ($boilerplate->getProcedureId() !== $statement->getProcedureId()) {
            $this->logger->warning('Ignored a boilerplate tag referencing a boilerplate from a different procedure', [
                'boilerplateId'          => $boilerplateId,
                'boilerplateProcedureId' => $boilerplate->getProcedureId(),
                'statementId'            => $statement->getId(),
                'statementProcedureId'   => $statement->getProcedureId(),
            ]);

            return;
        }

        $this->entityManager->persist(new BoilerplateUsage($boilerplate, $statement));
    }
}
