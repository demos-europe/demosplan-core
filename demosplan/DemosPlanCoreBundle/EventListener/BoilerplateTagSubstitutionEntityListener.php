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
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Mapping\PostLoad;

/**
 * Injects {@see BoilerplateTagSubstitutionService} into Statement entities after they
 * are loaded from the database.
 *
 * WHY THIS EXISTS:
 * {@see Statement::getRecommendation()} needs access to the service to substitute
 * <dp-boilerplate boilerplate-id="…"> tags with the boilerplate's current text
 * (DPLAN-18271). Entities are not managed by the Symfony container, so we use a
 * Doctrine postLoad entity listener to inject the service reference — the same
 * mechanism already established by {@see RecommendationVersionEntityListener} for the
 * unrelated version-tracking service. Kept as a separate listener rather than added to
 * that one: the two services solve unrelated problems, and Doctrine supports multiple
 * postLoad listeners per entity without conflict.
 *
 * LIMITATIONS:
 * This only fires for entities loaded from the database (via Doctrine hydration).
 * Entities created via `new Statement()` or `new Segment()` will NOT have the service
 * injected. Newly created statements and segments don't need substitution yet — they
 * either hold no recommendation text at all, or (if a caller sets one directly with a
 * tag already in it, which no code path does today) `getRecommendation()` falls back to
 * returning the raw property unsubstituted rather than throwing.
 *
 * @see Statement::setBoilerplateTagSubstitutionService()
 */
class BoilerplateTagSubstitutionEntityListener
{
    public function __construct(
        private readonly BoilerplateTagSubstitutionService $boilerplateTagSubstitutionService,
    ) {
    }

    /** @PostLoad */
    public function postLoad(Statement $statement, PostLoadEventArgs $event): void
    {
        $statement->setBoilerplateTagSubstitutionService($this->boilerplateTagSubstitutionService);
    }
}
