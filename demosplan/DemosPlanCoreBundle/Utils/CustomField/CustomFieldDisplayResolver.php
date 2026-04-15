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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;

class CustomFieldDisplayResolver
{
    public function __construct(
        private readonly CustomFieldProvider $customFieldProvider,
    ) {
    }

    /**
     * Resolves a list of custom field values to display-ready name/value pairs.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function resolveForDisplay(
        CustomFieldValuesList $values,
        CustomFieldSupportedEntity $sourceEntity,
        string $sourceEntityId,
        CustomFieldSupportedEntity $targetEntity,
    ): array {
        if ($values->isEmpty()) {
            return [];
        }

        $customFieldDefinitions = $this->customFieldProvider->getCustomFieldsByCriteria(
            $sourceEntity->value,
            $sourceEntityId,
            $targetEntity->value
        );

        $resolved = [];
        foreach ($values->getCustomFieldsValues() as $fieldValue) {
            $customFieldDefinition = $customFieldDefinitions->filter(
                fn (CustomFieldInterface $field) => $field->getId() === $fieldValue->getId()
            )->first();

            if (!$customFieldDefinition instanceof CustomFieldInterface) {
                continue;
            }

            $resolved[] = [
                'name'  => $customFieldDefinition->getName(),
                'value' => $customFieldDefinition->formatValueForDisplay($fieldValue->getValue()),
            ];
        }

        return $resolved;
    }
}
