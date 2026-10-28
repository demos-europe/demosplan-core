<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionTag;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\Querying\Contracts\PropertyPathInterface;

class AccessChecker
{
    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly CustomerService $currentCustomerService,
        private readonly DqlConditionFactory $conditionFactory,
    ) {
    }

    /**
     * Shared with the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType}
     * and {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType}.
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_institution_tag_create',
            'feature_institution_tag_read',
            'feature_institution_tag_update',
            'feature_institution_tag_delete',
        );
    }

    /**
     * Shared with the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagResourceType}.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getTagAccessConditions(): array
    {
        return $this->getCustomerScopedConditions(['category', 'customer', 'id']);
    }

    /**
     * Shared with the legacy {@see \demosplan\DemosPlanCoreBundle\ResourceTypes\InstitutionTagCategoryResourceType}.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getCategoryAccessConditions(): array
    {
        return $this->getCustomerScopedConditions(Paths::institutionTagCategory()->customer->id);
    }

    /**
     * In-memory counterpart of {@see self::getTagAccessConditions()}, for tags that are
     * already loaded (e.g. the tags assigned to an institution).
     */
    public function canRead(InstitutionTag $tag): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_read')
            && $tag->getCategory()->getCustomer()->getId() === $this->currentCustomerService->getCurrentCustomer()->getId();
    }

    /**
     * Tags and categories are only readable with the read permission, and only those of the current customer.
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    private function getCustomerScopedConditions(array|PropertyPathInterface $customerIdPath): array
    {
        if (!$this->currentUser->hasPermission('feature_institution_tag_read')) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                $customerIdPath
            ),
        ];
    }
}
