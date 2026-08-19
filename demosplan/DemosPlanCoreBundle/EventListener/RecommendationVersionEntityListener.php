<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\RecommendationVersionService;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Injects {@see RecommendationVersionService} into Statement entities after they are
 * loaded from the database.
 *
 * WHY THIS EXISTS:
 * {@see Statement::setRecommendation()} needs access to the service to record
 * recommendation versions. Entities are not managed by the Symfony container, so
 * we use a Doctrine postLoad entity listener to inject the service reference.
 *
 * LIMITATIONS:
 * This only fires for entities loaded from the database (via Doctrine hydration).
 * Entities created via `new Statement()` or `new Segment()` will NOT have the service
 * injected. Newly created statements and segments don't need version history — they
 * either hold the first recommendation directly on the entity field (exposed as virtual
 * version 1 at read time) or have no recommendation yet at all.
 *
 * @see Statement::setRecommendationVersionService()
 */
class RecommendationVersionEntityListener
{
    public function __construct(
        private readonly RecommendationVersionService $recommendationVersionService,
    ) {
    }

    /** @PostLoad */
    public function postLoad(Statement $statement, PostLoadEventArgs $event): void
    {
        $statement->setRecommendationVersionService($this->recommendationVersionService);
    }
}
