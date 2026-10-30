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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\PersistResourceException;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Doctrine\DBAL\Exception;

class SegmentCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
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

    public function removeOptionUsages(CustomFieldInterface $customField, array $deletedOptionIds): void
    {
        $segments = $this->segmentRepository->findSegmentsWithCustomField($customField->getId());

        foreach ($segments as $segment) {
            $this->removeDeletedOptionsFromSegment($segment, $customField, $deletedOptionIds);
        }
    }

    private function removeDeletedOptionsFromSegment(Segment $segment, CustomFieldInterface $customField, array $deletedOptionIds): void
    {
        $originalCustomFields = $segment->getCustomFields();
        if (!$originalCustomFields instanceof CustomFieldValuesList) {
            return;
        }

        $customFields = clone $originalCustomFields;
        $customFieldValue = $customFields->findById($customField->getId());
        if (!$customFieldValue instanceof CustomFieldValue) {
            return;
        }

        $currentValue = $customFieldValue->getValue();
        $remainingValue = $customField->removeOptionsFromValue($currentValue, $deletedOptionIds);
        if ($remainingValue === $currentValue) {
            return;
        }

        $customFields->removeCustomFieldValue($customFieldValue);

        // The clone is shallow, so add a new value object instead of changing the shared one
        if (null !== $remainingValue) {
            $replacement = new CustomFieldValue();
            $replacement->setId($customField->getId());
            $replacement->setValue($remainingValue);
            $customFields->addCustomFieldValue($replacement);
            $customFields->sortByFieldId();
        }

        $customFields->reindexValues();
        $segment->setCustomFields($customFields);
    }
}
