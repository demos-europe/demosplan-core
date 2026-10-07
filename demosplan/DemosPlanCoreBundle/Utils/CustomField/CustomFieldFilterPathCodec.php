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

use demosplan\DemosPlanCoreBundle\Logic\CustomField\CustomFieldFilterResolver;

/**
 * Encodes and decodes the `customField_<id>` condition path of a custom field in the segment list's
 * Drupal-style filter.
 *
 * The filter validation only accepts word characters in a path, so the hyphens of the UUID are left
 * out here. This differs from the statement filter, which keeps them, and from
 * {@see CustomFieldExportColumnKeyCodec}, which is about export columns.
 * The frontend counterpart is `client/js/lib/segment/customFieldFilterPath.js`.
 */
final class CustomFieldFilterPathCodec
{
    public static function forId(string $customFieldId): string
    {
        return CustomFieldFilterResolver::PREFIX.str_replace('-', '', $customFieldId);
    }

    /**
     * @return string|null the custom field id, null if the path is not the path of a custom field
     */
    public static function extractId(string $path): ?string
    {
        $pattern = '/^'.CustomFieldFilterResolver::PREFIX.'([0-9a-f]{8})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{4})([0-9a-f]{12})$/i';
        if (1 !== preg_match($pattern, $path, $parts)) {
            return null;
        }

        return implode('-', array_slice($parts, 1));
    }
}
