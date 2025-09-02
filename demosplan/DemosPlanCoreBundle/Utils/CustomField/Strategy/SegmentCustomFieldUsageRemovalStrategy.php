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
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;

class SegmentCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws PersistResourceException
     * @throws \Doctrine\DBAL\Exception
     */
    public function removeUsages(string $customFieldId): void
    {
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $segments = $this->segmentRepository->findSegmentsWithCustomField($customFieldId);

            foreach ($segments as $segment) {
                $this->removeCustomFieldFromSegment($segment, $customFieldId);
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (Exception $e) {
            // Rollback all changes on any error
            $this->entityManager->getConnection()->rollBack();

            // Clear entity manager to avoid stale state
            $this->entityManager->clear();

            // Re-throw with context
            throw new PersistResourceException("Failed to remove custom field values in segments for custom field ID {$customFieldId}: ".$e->getMessage(), 0, $e);
        }
    }

    public function supports(string $targetEntityClass): bool
    {
        return 'SEGMENT' === $targetEntityClass;
    }

    private function removeCustomFieldFromSegment(Segment $segment, string $customFieldId): void
    {
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

    public function removeOptionUsages(string $customFieldId, array $deletedOptionIds): void
    {
        $this->entityManager->getConnection()->beginTransaction();

        try {
            $segments = $this->segmentRepository->findSegmentsWithCustomField($customFieldId);

            foreach ($segments as $segment) {
                $this->removeDeletedOptionsFromSegment($segment, $customFieldId, $deletedOptionIds);
            }

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $this->entityManager->clear();

            throw new PersistResourceException("Failed to remove deleted option usages for custom field ID {$customFieldId}: ".$e->getMessage(), 0, $e);

        }
    }

    private function removeDeletedOptionsFromSegment(Segment $segment, string $customFieldId, array $deletedOptionIds): void
    {
        $customFields = clone $segment->getCustomFields();
        if ($customFields instanceof CustomFieldValuesList) {
            $customFieldValue = $customFields->findById($customFieldId);
            if ($customFieldValue instanceof CustomFieldValue
                && in_array($customFieldValue->getValue(), $deletedOptionIds, true)) {
                // Remove the entire custom field value if it references a deleted option
                $customFields->removeCustomFieldValue($customFieldValue);
                $customFields->reindexValues();
                $segment->setCustomFields($customFields);
            }
        }
    }
}
