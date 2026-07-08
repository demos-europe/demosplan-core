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
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;

class StatementCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly StatementRepository $statementRepository,
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
        // Statement custom fields cannot be updated when the procedure has statements
        // (blocked by ProcedureWithStatementsCustomFieldConstraint in CustomFieldUpdater),
        // so this path is unreachable in production.
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
