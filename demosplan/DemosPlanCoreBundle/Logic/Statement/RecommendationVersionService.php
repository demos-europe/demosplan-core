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

use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Single entry point for creating and reading recommendation versions.
 *
 * Two hooks call this service:
 * - Hook A: {@see Statement::setRecommendation()} — for all ORM-based recommendation changes.
 *   The service is injected into the entity via a postLoad entity listener.
 * - Hook B: {@see SegmentService::editSegmentRecommendations()}
 *   — for DQL-based bulk edit changes that bypass the setter.
 *
 * Both hooks pass the OLD and NEW recommendation text. The service decides whether
 * to create a version based on the old value and existing version history.
 *
 * We only store OLD recommendation texts (the state BEFORE each update). The current
 * recommendation is always on {@see Statement::getRecommendation()}. At read time,
 * a virtual version 1 is derived if no stored versions exist but the recommendation
 * is non-empty.
 */
class RecommendationVersionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecommendationVersionRepository $repository,
    ) {
    }

    /**
     * Records a recommendation version storing the OLD recommendation text
     * before it gets overwritten.
     *
     * Called BEFORE the recommendation is changed on the entity:
     * - From {@see Statement::setRecommendation()} — before $this->recommendation is overwritten
     * - From {@see SegmentService::editSegmentRecommendations()}
     *   — before DQL UPDATE runs
     *
     * Decision logic (no ID check needed, no DB query needed):
     * 1. If old and new recommendation are identical → no change, skip.
     * 2. If old recommendation is empty AND no existing versions → first recommendation
     *    being set (or new entity). Skip. Virtual version 1 handles it at read time.
     * 3. If old recommendation is empty AND existing versions exist → recommendation was
     *    cleared previously, now being set again. Store the empty old text as a version.
     * 4. If old recommendation is non-empty → real update. Store the old text as a version.
     *
     * Does not call flush() — the caller's transaction handles flushing.
     *
     * @return RecommendationVersion|null the newly created version entity, or null if
     *                                    skipped (no change, or first recommendation being set)
     */
    public function recordVersion(Statement $statement, string $oldRecommendation, string $newRecommendation): ?RecommendationVersion
    {
        if ($oldRecommendation === $newRecommendation) {
            return null;
        }

        $latestVersionNumber = $this->repository->getLatestVersionNumber($statement->getId());

        if ('' === $oldRecommendation && 0 === $latestVersionNumber) {
            return null;
        }

        $nextVersionNumber = ++$latestVersionNumber;

        $version = new RecommendationVersion();
        $version->setStatement($statement);
        $version->setVersionNumber($nextVersionNumber);
        $version->setRecommendationText($oldRecommendation);

        $this->entityManager->persist($version);

        return $version;
    }
}
