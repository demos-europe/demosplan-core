<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation\Factory;

use demosplan\DemosPlanCoreBundle\CustomField\AbstractOptionsCustomField;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use Ramsey\Uuid\Uuid;

abstract class AbstractSelectFieldFactory implements CustomFieldFactoryInterface
{
    abstract protected function createField(): AbstractOptionsCustomField;

    public function create(array $attributes): CustomFieldInterface
    {
        $field = $this->createField();
        $field->setFieldType($attributes['fieldType']);
        $field->setName($attributes['name']);
        $field->setDescription($attributes['description']);
        $field->setRequired($attributes['isRequired'] ?? false);
        $field->setOptions($this->normalizeOptions($attributes['options']));

        return $field;
    }

    /**
     * Ensure options are in the new object format.
     */
    private function normalizeOptions(array $options): array
    {
        $normalizedOptions = [];
        $optionPrototype = new CustomFieldOption();

        foreach ($options as $option) {
            // Already in new format or ensure it has required keys
            $customFieldOption = clone $optionPrototype;
            $customFieldOption->setId(Uuid::uuid4()->toString());
            $customFieldOption->setLabel($option['label']);
            $normalizedOptions[] = $customFieldOption;
        }

        return $normalizedOptions;
    }
}
