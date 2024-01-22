<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;

/**
 * @template-extends DplanResourceType<FaqCategory>
 *
 * @property-read CustomerResourceType $customer
 */
class FaqCategoryResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'FaqCategory';
    }

    protected function getProperties(): array
    {
        return [];
    }

    public function getEntityClass(): string
    {
        return FaqCategory::class;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->false()];
    }
}
