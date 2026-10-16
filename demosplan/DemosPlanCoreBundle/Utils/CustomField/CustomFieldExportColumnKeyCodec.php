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

/**
 * Encodes and decodes the `customField_<id>` key format shared by the segment export's column
 * definitions, column selection, export data rows and the custom field conditions of its filter.
 */
final class CustomFieldExportColumnKeyCodec
{
    private const PREFIX = 'customField_';

    public static function forId(string $customFieldId): string
    {
        return self::PREFIX.$customFieldId;
    }

    /**
     * @return string|null the custom field id, null if the key is not a custom field key
     */
    public static function extractId(string $key): ?string
    {
        return self::extractIds([$key])[0] ?? null;
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
