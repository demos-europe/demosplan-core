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

use InvalidArgumentException;

class CustomFieldMapper
{
    public const SOURCE_TO_TARGET_MAP = [
        'PROCEDURE' => 'SEGMENT',
    ];

    public function getTargetBySource(string $sourceEntityClass): string
    {
        if (!array_key_exists($sourceEntityClass, self::SOURCE_TO_TARGET_MAP)) {
            throw new InvalidArgumentException(sprintf('Source Class "%s" is not mapped to any target class.', $sourceEntityClass));
        }

        return self::SOURCE_TO_TARGET_MAP[$sourceEntityClass];
    }
}
