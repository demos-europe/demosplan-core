<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableInstitution;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

class AccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $currentCustomerService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Shared with the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InvitableInstitutionResourceType}
     * (get, list and readable properties).
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_institution_tag_assign',
            'feature_institution_tag_read'
        );
    }

    public function canReadCustomFields(): bool
    {
        return $this->currentUser->hasPermission('feature_organisations_custom_fields');
    }

    /**
     * Shared with the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InvitableInstitutionResourceType}.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        return [
            $this->conditionFactory->propertyHasValue(false, Paths::orga()->deleted),
            $this->conditionFactory->propertyHasValue(
                OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
                Paths::orga()->statusInCustomers->status
            ),
            $this->conditionFactory->propertyHasValue(
                RoleInterface::GPSORG,
                Paths::orga()->users->roleInCustomers->role->groupCode
            ),
            $this->conditionFactory->propertyHasValue(
                OrgaTypeInterface::PUBLIC_AGENCY,
                Paths::orga()->statusInCustomers->orgaType->name
            ),
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                Paths::orga()->statusInCustomers->customer->id
            ),
        ];
    }
}
