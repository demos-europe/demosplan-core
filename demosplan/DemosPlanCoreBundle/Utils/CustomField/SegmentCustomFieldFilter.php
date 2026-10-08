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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Helpers to filter segments by their SEGMENT custom field values (select fields, by option id).
 * Within one custom field any selected option matches, across custom fields every field must match.
 *
 * The values are stored as JSON text like `[{"id":"<fieldId>","value":"<optionId>"|["<optionId>"]}]`.
 * Option ids are UUIDs, so a segment holds an option if its text contains `"<optionId>"`.
 */
class SegmentCustomFieldFilter
{
    /**
     * Query parameter key, e.g. `customField[<fieldId>][]=<optionId>`.
     */
    public const FILTER_KEY = 'customField';

    private const PERMISSION = 'field_segments_custom_fields';

    public function __construct(private readonly PermissionsInterface $permissions)
    {
    }

    /**
     * The query parameter filter ignores selections of users who may not see the custom fields,
     * rather than rejecting the request. Bulk edit and exports are gated by their own permissions.
     */
    public function isFilteringAllowed(): bool
    {
        return $this->permissions->hasPermission(self::PERMISSION);
    }

    /**
     * Checks that every key is a field id (a UUID). The rest of the shape is checked by the
     * `constraints` of the `customField` parameter in the resource, which cannot check array keys.
     *
     * @param array<mixed> $selections
     *
     * @phpstan-assert array<string, list<string>> $selections
     *
     * @throws BadRequestHttpException if a key is not a UUID
     */
    public function assertValidSelections(array $selections): void
    {
        foreach (array_keys($selections) as $fieldId) {
            if (!is_string($fieldId) || !Uuid::isValid($fieldId)) {
                throw new BadRequestHttpException('Invalid customField filter key.');
            }
        }
    }

    /**
     * LIKE pattern that matches the stored JSON of segments holding the option.
     * The option id must be a UUID (checked by the resource constraints), so it holds no wildcards.
     */
    public function getOptionLikePattern(string $optionId): string
    {
        return '%"'.$optionId.'"%';
    }
}
