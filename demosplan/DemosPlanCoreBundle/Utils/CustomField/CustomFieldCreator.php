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
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;

class CustomFieldCreator extends CoreService
{
    public function __construct(private readonly CustomFieldFactory $customFieldFactory, private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository)
    {
    }

    public function createCustomField($attributes): CustomFieldInterface
    {
        /** @var CustomFieldInterface $particularCustomField */
        $particularCustomField = $this->customFieldFactory->createCustomField($attributes);

        /** @var CustomFieldConfiguration $customFieldConfiguration */
        $customFieldConfiguration = $this->customFieldConfigurationRepository->findOrCreateCustomFieldConfigurationByCriteria($attributes['sourceEntity'], $attributes['sourceEntityId'], $attributes['targetEntity']);
        $customFieldConfiguration->addCustomFieldToCustomFieldList($particularCustomField);
        $this->customFieldConfigurationRepository->updateObject($customFieldConfiguration);

        return $particularCustomField;
    }
}
