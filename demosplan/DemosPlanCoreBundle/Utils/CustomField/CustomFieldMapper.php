<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use InvalidArgumentException;

class CustomFieldMapper
{
    public const SOURCE_TO_TARGET_MAP = [
        'PROCEDURE' => 'SEGMENT'
    ];

    public function getTargetBySource(string $sourceEntityClass): string
    {
        if (!array_key_exists($sourceEntityClass, self::SOURCE_TO_TARGET_MAP)) {
            throw new InvalidArgumentException(sprintf('Source Class "%s" is not mapped to any target class.', $sourceEntityClass));
        }

        return self::SOURCE_TO_TARGET_MAP[$sourceEntityClass];
    }
}
