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
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;

class OrgaCustomFieldUsageRemovalStrategy implements EntityCustomFieldUsageRemovalStrategyInterface
{
    public function __construct(
        private readonly OrgaRepository $orgaRepository,
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
        // Orga currently supports only text custom fields, which have no options.
        // When option-bearing field types (single/multi-select) are added for Orga,
        // implement this analogously to removeUsages, deciding per field type
        // whether to drop the value or trim deleted option ids from it.
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
