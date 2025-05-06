<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;


class CustomFieldCreator extends CoreService
{
    public function __construct(private readonly CustomFieldFactory $customFieldFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function createCustomField($attributes): CustomFieldInterface
    {
        $createdCustomField = $this->customFieldFactory->createCustomField($attributes);
        $customFieldConfiguration = $this->customFieldConfigurationRepository->createCustomFieldConfiguration(
            $attributes['sourceEntity'],
            $attributes['sourceEntityId'],
            $attributes['targetEntity'],
            $createdCustomField
        );

        $createdCustomField->setId($customFieldConfiguration->getId());
        return $createdCustomField;
    }
}
