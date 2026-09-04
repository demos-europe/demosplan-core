<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\CustomField;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;

/**
 * Access rules for custom field definitions, shared by the provider and the processor so that reads
 * and writes cannot drift apart. Mirrors
 * {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType::isCreateAllowed()}/
 * isUpdateAllowed()/isDeleteAllowed()/isGetAllowed()/isListAllowed()/getAccessConditions().
 */
class CustomFieldAccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $customerService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions('area_admin_custom_fields', 'feature_organisations_custom_fields');
    }

    /**
     * Additionally covers `feature_statements_custom_fields`, which the legacy resource granted only for
     * listing - relevant here since, unlike the legacy resource, this one actually implements
     * `GetCollection`.
     */
    public function isListAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_custom_fields',
            'feature_statements_custom_fields',
            'feature_organisations_custom_fields'
        );
    }

    /**
     * For a CUSTOMER-scoped custom field, the row must belong to the current customer. Non-CUSTOMER
     * sources (PROCEDURE etc.) keep their existing behaviour - their sourceEntityId is still supplied by
     * the caller as a filter and trusted as-is.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        $customerId = $this->customerService->getCurrentCustomer()->getId();

        return [
            $this->conditionFactory->anyConditionApplies(
                $this->conditionFactory->propertyHasNotValue(
                    CustomFieldSupportedEntity::customer->value,
                    ['sourceEntityClass']
                ),
                $this->conditionFactory->allConditionsApply(
                    $this->conditionFactory->propertyHasValue(
                        CustomFieldSupportedEntity::customer->value,
                        ['sourceEntityClass']
                    ),
                    $this->conditionFactory->propertyHasValue(
                        $customerId,
                        ['sourceEntityId']
                    )
                )
            ),
        ];
    }
}
