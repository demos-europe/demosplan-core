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
use Doctrine\ORM\EntityManagerInterface;

class CustomFieldDeleter
{
    public function __construct(
        private readonly CustomFieldConfigurationRepository $customFieldConfigurationRepository,
        private readonly SegmentRepository $segmentRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function deleteCustomField(string $entityId): void
    {
        $customFieldConfiguration = $this->getCustomFieldConfiguration($entityId);
        
        // Remove all segment usages of this custom field
        $this->removeSegmentUsages($entityId);
        
        // Delete the custom field configuration
        $this->customFieldConfigurationRepository->delete($customFieldConfiguration->getId());
        $this->entityManager->flush();
    }


    private function getCustomFieldConfiguration(string $entityId): CustomFieldConfiguration
    {
        $customFieldConfiguration = $this->customFieldConfigurationRepository->find($entityId);
        
        if (!$customFieldConfiguration) {
            throw new InvalidArgumentException("CustomFieldConfiguration with ID '{$entityId}' not found");
        }
        
        return $customFieldConfiguration;
    }

    private function removeSegmentUsages(string $customFieldId): void
    {
        // Get all segments that have this custom field
        $segments = $this->segmentRepository->findSegmentsWithCustomField($customFieldId);
        
        foreach ($segments as $segment) {
            $customFields = $segment->getCustomFields();
            if ($customFields instanceof CustomFieldValuesList) {
                $customFieldValue = $customFields->findById($customFieldId);
                if ($customFieldValue instanceof CustomFieldValue) {
                    $customFields->removeCustomFieldValue($customFieldValue);
                    $customFields->reindexValues();
                    $segment->setCustomFields($customFields);
                }
            }
        }
        
        $this->entityManager->flush();
    }

}