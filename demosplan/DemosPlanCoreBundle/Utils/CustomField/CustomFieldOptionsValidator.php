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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\EntityManagerInterface;


class CustomFieldOptionsValidator
{
    public function validate(array $options, string $fieldType): void
    {
        $this->validateBasicStructure($options);
        $this->validateFieldTypeSpecific($options, $fieldType);
    }

    private function validateBasicStructure(array $options): void
    {
        foreach ($options as $option) {
            if (!isset($option['label']) || empty(trim($option['label']))) {
                throw new InvalidArgumentException('All options must have a non-empty label');
            }
        }
    }

    private function validateFieldTypeSpecific(array $options, string $fieldType): void
    {
        match ($fieldType) {
            'singleSelect' => $this->validateRadioButtonOptions($options),
            //'select' => $this->validateSelectOptions($options),
            // Future field types can be added here
            default => null, // No specific validation needed
        };
    }

    private function validateRadioButtonOptions(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Radio button fields must have at least 2 options');
        }
    }

    private function validateSelectOptions(array $options): void
    {
        if (empty($options)) {
            throw new InvalidArgumentException('Select fields must have at least 1 option');
        }
    }

}
