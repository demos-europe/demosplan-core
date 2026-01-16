<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;

class CustomFieldProvider
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
    ) {
    }


    public function getCustomFieldsByCriteria(string $sourceEntity, string $sourceEntityId, string $targetEntity): ArrayCollection
    {
        return $this->customFieldConfigurationRepository->getCustomFields($sourceEntity,$sourceEntityId, $targetEntity);
    }
}
