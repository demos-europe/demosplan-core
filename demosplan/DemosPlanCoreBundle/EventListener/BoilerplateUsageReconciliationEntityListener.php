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
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateUsageReconciliationService;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Injects {@see BoilerplateUsageReconciliationService} into Statement entities after
 * they are loaded from the database.
 *
 * WHY THIS EXISTS:
 * {@see Statement::setRecommendation()} needs access to the service to reconcile
 * {@see BoilerplateUsage} rows against the <dp-boilerplate boilerplate-id="…"> tags in
 * the new recommendation text (DPLAN-18271). Entities are not managed by the Symfony
 * container, so we use a Doctrine postLoad entity listener to inject the service
 * reference — the same mechanism already established by
 * {@see RecommendationVersionEntityListener} and
 * {@see BoilerplateTagSubstitutionEntityListener}. Kept as its own listener rather than
 * folded into either of those: each service solves an unrelated problem, and Doctrine
 * supports multiple postLoad listeners per entity without conflict.
 *
 * LIMITATIONS:
 * This only fires for entities loaded from the database (via Doctrine hydration).
 * Entities created via `new Statement()` or `new Segment()` will NOT have the service
 * injected — reconciliation is simply skipped for such entities (see
 * {@see Statement::setRecommendation()}), same graceful-degradation trade-off already
 * accepted for the other two postLoad-injected services on this entity.
 *
 * @see Statement::setBoilerplateUsageReconciliationService()
 */
class BoilerplateUsageReconciliationEntityListener
{
    public function __construct(
        private readonly BoilerplateUsageReconciliationService $boilerplateUsageReconciliationService,
    ) {
    }

    /** @PostLoad */
    public function postLoad(Statement $statement, PostLoadEventArgs $event): void
    {
        $statement->setBoilerplateUsageReconciliationService($this->boilerplateUsageReconciliationService);
    }
}
