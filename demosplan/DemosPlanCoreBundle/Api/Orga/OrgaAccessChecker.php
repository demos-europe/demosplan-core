<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Orga;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Mirrors OrgaResourceType::isAvailable()/getAccessConditions(), so the ApiPlatform
 * Orga endpoint enforces the same rules as the legacy JSON:API one instead of
 * exposing every organisation to any caller.
 */
class OrgaAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $currentCustomerService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $extendedOrgaAccess = $this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist',
            'feature_organisation_user_list'
        ) && !$this->currentUser->hasAnyPermissions('feature_organisation_own_users_list');

        $mandatoryConditions = $this->getMandatoryConditions();

        // permissions allow the user to access all organisation resources
        if ($extendedOrgaAccess) {
            return $mandatoryConditions;
        }

        // if no special permissions are given, the user can at least access its own organisation
        $organisationId = $this->currentUser->getUser()->getOrga()->getId();
        $mandatoryConditions[] = $this->conditionFactory->propertyHasValue($organisationId, Paths::orga()->id);

        return $mandatoryConditions;
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    private function getMandatoryConditions(): array
    {
        // Regardless of permissions or organisation affiliation we never show deleted organisations
        // or organisations of a foreign customer.
        return [
            $this->conditionFactory->propertyHasValue(false, Paths::orga()->deleted),
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                Paths::orga()->statusInCustomers->customer->id
            ),
            $this->conditionFactory->propertyHasNotValue(User::ANONYMOUS_USER_ORGA_ID, Paths::orga()->id),
        ];
    }
}
