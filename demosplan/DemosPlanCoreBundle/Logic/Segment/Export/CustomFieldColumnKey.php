<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

/**
 * Builds and parses the `customField_<id>` column key format shared by the segment export's
 * column definitions, column selection, and export data rows.
 */
final class CustomFieldColumnKey
{
    private const PREFIX = 'customField_';

    public static function forId(string $customFieldId): string
    {
        return self::PREFIX.$customFieldId;
    }

    /**
     * @param string[] $selectedColumnKeys
     *
     * @return string[]
     */
    public static function extractIds(array $selectedColumnKeys): array
    {
        return array_values(preg_filter('/^'.self::PREFIX.'(.+)$/', '$1', $selectedColumnKeys));
    }
}
