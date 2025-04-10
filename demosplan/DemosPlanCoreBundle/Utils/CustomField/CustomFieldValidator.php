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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldList;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class CustomFieldValidator
{
    private array $sourceToTargetMap;

    public function __construct(array $sourceToTargetMap = [
        'PROCEDURE'          => 'SEGMENT',
        'PROCEDURE_TEMPLATE' => 'SEGMENT',
    ])
    {
        $this->sourceToTargetMap = $sourceToTargetMap;
    }

    public function validate(array $attributes): void
    {
        $this->validateFieldType($attributes['fieldType']);
        $this->validateSourceToTargetMapping(
            $attributes['sourceEntity'],
            $attributes['targetEntity']
        );
    }

    private function validateFieldType(?string $fieldType): void
    {
        if (!isset(CustomFieldList::TYPE_CLASSES[$fieldType])) {
            throw new InvalidArgumentException('Unknown custom field type: '.$fieldType);
        }
    }

    private function validateSourceToTargetMapping(?string $sourceEntity, ?string $targetEntity): void
    {
        if ($this->sourceToTargetMap[$sourceEntity] !== $targetEntity) {
            throw new InvalidArgumentException(sprintf('The target entity "%s" does not match the expected target entity "%s" for source entity "%s".', $targetEntity, $this->sourceToTargetMap[$sourceEntity], $sourceEntity));
        }
    }
}
