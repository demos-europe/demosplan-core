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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\CustomFieldOptionRemovalStrategyFactory;

class OrgaCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly OrgaRepository $orgaRepository,
        private readonly CustomFieldConfigurationRepository $configRepository,
        private readonly CustomFieldOptionRemovalStrategyFactory $optionRemovalStrategyFactory,
    ) {
    }

    public function supports(string $targetEntityClass): bool
    {
        return 'ORGA' === $targetEntityClass;
    }

    public function removeUsages(string $customFieldId): void
    {
        $orgas = $this->orgaRepository->findOrgasWithCustomField($customFieldId);

        foreach ($orgas as $orga) {
            $this->removeCustomFieldFromOrga($orga, $customFieldId);
        }
    }

    public function removeOptionUsages(string $customFieldId, array $deletedOptionIds): void
    {
        $fieldType = $this->configRepository->find($customFieldId)->getConfiguration()->getFieldType();
        $strategy = $this->optionRemovalStrategyFactory->createForFieldType($fieldType);

        $orgas = $this->orgaRepository->findOrgasWithCustomField($customFieldId);

        foreach ($orgas as $orga) {
            $originalCustomFields = $orga->getCustomFields();
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
            $orga->setCustomFields($customFields);
        }
    }

    private function removeCustomFieldFromOrga(Orga $orga, string $customFieldId): void
    {
        $originalCustomFields = $orga->getCustomFields();
        if (!$originalCustomFields instanceof CustomFieldValuesList) {
            return;
        }
        $customFields = clone $originalCustomFields;
        $customFieldValue = $customFields->findById($customFieldId);
        if ($customFieldValue instanceof CustomFieldValue) {
            $customFields->removeCustomFieldValue($customFieldValue);
            $customFields->reindexValues();
            $orga->setCustomFields($customFields);
        }
    }
}
