<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\CustomFieldOptionRemovalStrategyFactory;
use Doctrine\DBAL\Exception;

class SegmentCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly CustomFieldConfigurationRepository $configRepository,
        private readonly CustomFieldOptionRemovalStrategyFactory $optionRemovalStrategyFactory,
    ) {
    }

    /**
     * @throws PersistResourceException
     * @throws Exception
     */
    public function removeUsages(string $customFieldId): void
    {
        $segments = $this->segmentRepository->findSegmentsWithCustomField($customFieldId);

        foreach ($segments as $segment) {
            $this->removeCustomFieldFromSegment($segment, $customFieldId);
        }
    }

    public function supports(string $targetEntityClass): bool
    {
        return 'SEGMENT' === $targetEntityClass;
    }

    public function removeOptionUsages(string $customFieldId, array $deletedOptionIds): void
    {
        $fieldType = $this->configRepository->find($customFieldId)->getConfiguration()->getFieldType();
        $strategy = $this->optionRemovalStrategyFactory->createForFieldType($fieldType);

        $segments = $this->segmentRepository->findSegmentsWithCustomField($customFieldId);

        foreach ($segments as $segment) {
            $originalCustomFields = $segment->getCustomFields();
            if (!$originalCustomFields instanceof CustomFieldValuesList) {
                continue;
            }
            $customFields = clone $originalCustomFields;
            $currentValue = $customFields->findById($customFieldId);
            if (!$currentValue instanceof CustomFieldValue) {
                continue;
            }
            $updatedValue = $strategy->removeOptionUsage($currentValue, $deletedOptionIds);
            $customFields->removeCustomFieldValue($currentValue);
            if (null !== $updatedValue) {
                $customFields->addCustomFieldValue($updatedValue);
            }
            $customFields->reindexValues();
            $segment->setCustomFields($customFields);
        }
    }

    private function removeCustomFieldFromSegment(Segment $segment, string $customFieldId): void
    {
        $originalCustomFields = $segment->getCustomFields();
        if (!$originalCustomFields instanceof CustomFieldValuesList) {
            return;
        }
        $customFields = clone $segment->getCustomFields();
        if ($customFields instanceof CustomFieldValuesList) {
            $customFieldValue = $customFields->findById($customFieldId);
            if ($customFieldValue instanceof CustomFieldValue) {
                $customFields->removeCustomFieldValue($customFieldValue);
                $customFields->reindexValues();
                $segment->setCustomFields($customFields);
            }
        }
    }
}
