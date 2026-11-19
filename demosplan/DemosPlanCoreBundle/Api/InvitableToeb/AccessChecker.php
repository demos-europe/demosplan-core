<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableToeb;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Mirrors {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InvitablePublicAgencyResourceType}, so the API Platform
 * endpoint enforces the same rules as the legacy one.
 */
class AccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $currentCustomerService,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly ProcedureRepository $procedureRepository,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_main_procedures',
            'area_admin_invitable_institution'
        );
    }

    /**
     * Accepted public agencies of the current customer that are listed and not invited to the current procedure
     * yet. Without a current procedure nothing is accessible.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        // The ids are read directly, hydrating every invited organisation is not needed here.
        $invitedOrgaIds = $this->procedureRepository->getInvitedOrgaIds($procedure->getId());

        return [
            $this->conditionFactory->propertyHasValue(false, Paths::orga()->deleted),
            $this->conditionFactory->propertyHasValue(true, Paths::orga()->statusInCustomers->showlist),
            $this->conditionFactory->propertyHasValue(
                OrgaTypeInterface::PUBLIC_AGENCY,
                Paths::orga()->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                Paths::orga()->statusInCustomers->customer->id
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                Paths::orga()->statusInCustomers->status
            ),
            [] === $invitedOrgaIds
                ? $this->conditionFactory->true()
                : $this->conditionFactory->propertyHasNotAnyOfValues($invitedOrgaIds, Paths::orga()->id),
        ];
    }
}
