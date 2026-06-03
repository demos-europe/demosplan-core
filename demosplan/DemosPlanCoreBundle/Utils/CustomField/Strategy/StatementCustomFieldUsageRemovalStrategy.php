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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\CustomFieldOptionRemovalStrategyFactory;

class StatementCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
        private readonly CustomFieldConfigurationRepository $configRepository,
        private readonly CustomFieldOptionRemovalStrategyFactory $optionRemovalStrategyFactory,
    ) {
    }

    public function supports(string $targetEntityClass): bool
    {
        return 'STATEMENT' === $targetEntityClass;
    }

    public function removeUsages(string $customFieldId): void
    {
        $statements = $this->statementRepository->findStatementsWithCustomField($customFieldId);

        foreach ($statements as $statement) {
            $this->removeCustomFieldFromStatement($statement, $customFieldId);
        }
    }

    public function removeOptionUsages(string $customFieldId, array $deletedOptionIds): void
    {
        $fieldType = $this->configRepository->find($customFieldId)->getConfiguration()->getFieldType();
        $strategy = $this->optionRemovalStrategyFactory->createForFieldType($fieldType);

        $statements = $this->statementRepository->findStatementsWithCustomField($customFieldId);

        foreach ($statements as $statement) {
            $originalCustomFields = $statement->getCustomFields();
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
            $statement->setCustomFields($customFields);
        }
    }

    private function removeCustomFieldFromStatement(Statement $statement, string $customFieldId): void
    {
        $originalCustomFields = $statement->getCustomFields();
        if (!$originalCustomFields instanceof CustomFieldValuesList) {
            return;
        }
        $customFields = clone $originalCustomFields;
        $customFieldValue = $customFields->findById($customFieldId);
        if ($customFieldValue instanceof CustomFieldValue) {
            $customFields->removeCustomFieldValue($customFieldValue);
            $customFields->reindexValues();
            $statement->setCustomFields($customFields);
        }
    }
}
