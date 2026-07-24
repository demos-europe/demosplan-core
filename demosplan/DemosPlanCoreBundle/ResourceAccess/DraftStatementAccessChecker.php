<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceAccess;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

class DraftStatementAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_statements_draft');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isAvailable();
    }

    /**
     * Mirrors DraftStatementResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        $user = $this->currentUser->getUser();
        if (!$user instanceof User) {
            return [$this->conditionFactory->false()];
        }

        return [
            // Current procedure only
            $this->conditionFactory->propertyHasValue($procedure->getId(), ['procedure', 'id']),

            // Not deleted
            $this->conditionFactory->propertyHasValue(false, ['deleted']),

            // Same organization
            $this->conditionFactory->propertyHasValue($user->getOrganisationId(), ['organisation', 'id']),

            // Own drafts only (works for all user types)
            $this->conditionFactory->propertyHasValue($user->getId(), ['user', 'id']),
        ];
    }
}
