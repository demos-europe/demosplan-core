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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\EntityCustomFieldUsageStrategyFactory;
use Doctrine\ORM\EntityManagerInterface;

class CustomFieldDeleter
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository    $customFieldConfigurationRepository,
        private readonly EntityCustomFieldUsageStrategyFactory $entityCustomFieldUsageStrategyFactory,
    ) {
    }

    public function deleteCustomField(string $entityId): void
    {
        // Get the CustomFieldConfiguration from database
        /** @var CustomFieldConfiguration $customFieldConfiguration */
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($entityId);

        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$entityId}' not found");
        }

        // 1. Entity-specific cleanup (replaces hardcoded removeSegmentUsages)
        $entityStrategy = $this->entityCustomFieldUsageStrategyFactory->createUsageRemovalStrategy($customFieldConfiguration->getTargetEntityClass());
        $entityStrategy->removeUsages($entityId);


        // 2. Delete the custom field configuration
        $this->customFieldConfigurationRepository->deleteObject($customFieldConfiguration);
    }

}
