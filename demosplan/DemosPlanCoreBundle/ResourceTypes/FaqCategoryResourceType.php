<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\Querying\Contracts\PathsBasedInterface;

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

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return false;
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->conditionFactory->false();
    }
}
