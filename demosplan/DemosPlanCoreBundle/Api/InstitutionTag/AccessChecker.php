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
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
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
     * Mirrors InstitutionTagResourceType::isAvailable().
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
     * Mirrors InstitutionTagResourceType::getAccessConditions().
     *
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function getAccessConditions(): array
    {
        if (!$this->currentUser->hasPermission('feature_institution_tag_read')) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                ['category', 'customer', 'id']
            ),
        ];
    }

    /**
     * In-memory counterpart of {@see self::getAccessConditions()}, for tags that are
     * already loaded (e.g. the tags assigned to an institution).
     */
    public function canRead(InstitutionTag $tag): bool
    {
        return $this->currentUser->hasPermission('feature_institution_tag_read')
            && $tag->getCategory()->getCustomer()->getId() === $this->currentCustomerService->getCurrentCustomer()->getId();
    }
}
